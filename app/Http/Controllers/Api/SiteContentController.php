<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteContent;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SiteContentController extends Controller
{
    /**
     * What a rich text field is allowed to contain. Anything the editor can
     * produce is here, and nothing else gets through.
     */
    protected const ALLOWED_RICH_TEXT_TAGS = ['p', 'br', 'strong', 'em', 's', 'u', 'ul', 'ol', 'li', 'h3', 'a'];

    /**
     * Tags whose contents are code rather than words. Unwrapping these would
     * leave the code on the page as text, so they go entirely.
     */
    protected const DROPPED_RICH_TEXT_TAGS = ['script', 'style', 'iframe', 'svg', 'noscript', 'template', 'object', 'embed'];

    /**
     * The whole site copy for one language, section by section. Public, because
     * this is what the visitor facing pages read. Anything nobody has translated
     * reads English, and anything never saved at all falls back to the defaults
     * in config/site_content.php.
     */
    public function index(Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);
        [$english, $translated] = $this->storedFor($locale);

        $content = [];

        foreach (config('site_content') as $section => $defaults) {
            $content[$section] = $this->present(
                $section,
                $english->get($section)?->content ?? [],
                $translated->get($section)?->content ?? []
            );
        }

        return response()->json($content);
    }

    /**
     * What the content editor works on: the raw values for this language, plus
     * the English alongside so an untranslated field can show what the site will
     * actually render. Merging the two here would let a save write the English
     * copy into the translation and lose the fallback for good.
     */
    public function adminIndex(Request $request, string $locale): JsonResponse
    {
        $this->ensureCanEditContent();

        if (! in_array($locale, config('locales'), true)) {
            abort(404, __('messages.unknown_locale'));
        }

        [$english, $translated] = $this->storedFor($locale);

        $content = [];
        $fallback = [];

        foreach (config('site_content') as $section => $defaults) {
            $fallback[$section] = $this->present($section, $english->get($section)?->content ?? []);
            $content[$section] = $locale === 'en'
                ? $fallback[$section]
                : ($translated->get($section)?->content ?? []);
        }

        return response()->json([
            'locale' => $locale,
            'content' => $content,
            'fallback' => $fallback,
        ]);
    }

    /**
     * The saved rows for one language and the English they fall back to, both
     * keyed by section. One query rather than two, since English is usually
     * wanted alongside whatever else was asked for.
     */
    protected function storedFor(string $locale): array
    {
        $stored = SiteContent::whereIn('locale', array_unique([$locale, 'en']))->get();

        return [
            $stored->where('locale', 'en')->keyBy('section'),
            $locale === 'en' ? collect() : $stored->where('locale', $locale)->keyBy('section'),
        ];
    }

    /**
     * The frontend asks for a language outright. A caller that does not say
     * falls back to whatever the Accept-Language middleware worked out.
     */
    protected function resolveLocale(Request $request): string
    {
        $locale = $request->query('locale', app()->getLocale());

        return in_array($locale, config('locales'), true) ? $locale : 'en';
    }

    /**
     * Save one section. Only the fields declared in config are accepted, so a
     * stray input on the form cannot write anything unexpected.
     */
    public function update(Request $request, string $section): JsonResponse
    {
        $this->ensureCanEditContent();

        $locale = $request->input('locale', 'en');

        if (! in_array($locale, config('locales'), true)) {
            abort(422, __('messages.unknown_locale'));
        }

        $defaults = config("site_content.{$section}");

        if (! $defaults) {
            abort(404, __('messages.unknown_section'));
        }

        // The photo and the video are the same building in every language, so
        // they live on the English row and every translation reads them there.
        if ($locale !== 'en') {
            unset($defaults['feature_image'], $defaults['video_url']);
        }

        $validated = $request->validate($this->rulesFor($defaults));

        $record = SiteContent::firstOrNew(['section' => $section, 'locale' => $locale]);
        $content = $record->content ?? [];

        foreach ($validated as $field => $value) {
            // A file arrives under feature_image, a plain value under everything else.
            if ($request->hasFile($field)) {
                continue;
            }

            $content[$field] = $this->isRichText($field) ? $this->sanitizeRichText((string) $value) : $value;
        }

        // A repeater posts its whole list every time, so a list that did not
        // arrive means every row was deleted rather than left alone.
        foreach ($defaults as $field => $default) {
            if (! is_array($default)) {
                continue;
            }

            $rows = [];

            foreach ($validated[$field] ?? [] as $row) {
                // A row the editor added but never filled in is not worth keeping.
                if (implode('', $row) !== '') {
                    $rows[] = $row;
                }
            }

            $content[$field] = $rows;
        }

        if ($request->hasFile('feature_image')) {
            $content['feature_image'] = $this->storeFeatureImage($request, $content['feature_image'] ?? null);
        }

        $record->content = $content;
        $record->save();

        $this->revalidateFrontend($locale);

        // What the site will actually render, which for a translation means the
        // English underneath it rather than the row that was just saved.
        $english = $locale === 'en'
            ? $content
            : (SiteContent::where(['section' => $section, 'locale' => 'en'])->first()?->content ?? []);

        return response()->json([
            'message' => __('messages.content_saved'),
            'section' => $section,
            'locale' => $locale,
            'content' => $this->present($section, $english, $locale === 'en' ? [] : $content),
        ]);
    }

    /**
     * Ask the public site to drop its cached copy. The content is already saved
     * by this point, so a frontend that cannot be reached must not fail the
     * request. It falls back to its own hourly refresh instead.
     */
    protected function revalidateFrontend(string $locale): void
    {
        $url = config('services.frontend.url');
        $secret = config('services.frontend.revalidate_secret');

        if (! $url || ! $secret) {
            Log::warning('Frontend revalidation is not configured, the public site will refresh on its own schedule.');

            return;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['X-Revalidate-Secret' => $secret])
                ->post(rtrim($url, '/').'/api/revalidate', ['locale' => $locale]);

            if ($response->failed()) {
                Log::error('The frontend refused the revalidation request.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('Could not reach the frontend to revalidate.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Every field is optional so a section can be saved a bit at a time. A
     * field whose default is a list is a repeater, and its rows get rules of
     * their own. The rest are plain text unless the name says otherwise.
     */
    protected function rulesFor(array $defaults): array
    {
        $rules = [];

        foreach ($defaults as $field => $default) {
            if (is_array($default)) {
                $rules[$field] = ['nullable', 'array', 'max:50'];

                foreach (array_keys($default[0]) as $key) {
                    $rules["{$field}.*.{$key}"] = ['nullable', 'string', 'max:5000'];
                }

                continue;
            }

            $rules[$field] = match (true) {
                $field === 'video_url' => ['nullable', 'url', 'max:2048'],
                $field === 'feature_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
                $this->isRichText($field) => ['nullable', 'string', 'max:20000'],
                default => ['nullable', 'string', 'max:5000'],
            };
        }

        return $rules;
    }

    /**
     * Fields ending in _body hold HTML from the editor. Naming them this way
     * is what tells both ends of the app to treat them as rich text.
     */
    protected function isRichText(string $field): bool
    {
        return str_ends_with($field, '_body');
    }

    /**
     * Rich text is stored as HTML, so it has to be cleaned before it goes in.
     * Only the tags the editor can produce survive and every attribute is
     * dropped bar a plain link, so saved copy cannot carry script or handlers.
     */
    protected function sanitizeRichText(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;

        // Malformed markup is expected here, we clean it rather than complain.
        $previousSetting = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"?><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body) {
            return '';
        }

        $this->stripDisallowedNodes($body);

        $clean = '';

        foreach ($body->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        return trim($clean);
    }

    /**
     * A tag that is not allowed is unwrapped rather than deleted, so the words
     * inside it survive as plain text.
     */
    protected function stripDisallowedNodes(DOMNode $node): void
    {
        // Snapshot the children, the live list shifts as we unwrap tags.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                $node->removeChild($child);

                continue;
            }

            if (in_array($child->nodeName, self::DROPPED_RICH_TEXT_TAGS, true)) {
                $node->removeChild($child);

                continue;
            }

            $this->stripDisallowedNodes($child);

            if (! in_array($child->nodeName, self::ALLOWED_RICH_TEXT_TAGS, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);

                continue;
            }

            $href = $child->nodeName === 'a' ? $child->getAttribute('href') : null;

            foreach (iterator_to_array($child->attributes) as $attribute) {
                $child->removeAttribute($attribute->nodeName);
            }

            // Anything other than a normal link, javascript: in particular, goes.
            if ($href && preg_match('#^(https?://|mailto:|/)#i', $href)) {
                $child->setAttribute('href', $href);
                $child->setAttribute('target', '_blank');
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    protected function storeFeatureImage(Request $request, ?string $currentUrl): string
    {
        // Replacing the image leaves the old file behind otherwise.
        if ($currentUrl) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $currentUrl));
        }

        $file = $request->file('feature_image');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();

        return Storage::url($file->storeAs('site-content', $filename, 'public'));
    }

    /**
     * Fill the gaps with the defaults and hand back an absolute image URL, since
     * the frontend is served from a different host.
     */
    protected function present(string $section, array $english, array $translated = []): array
    {
        // Three layers, weakest first: the defaults in config, whatever English
        // somebody saved over them, then the translation. A translated field
        // left empty is one nobody has got to yet, so it drops back to English.
        $filled = array_filter(
            $translated,
            fn ($value) => $value !== null && $value !== '' && $value !== []
        );

        $presented = array_merge(config("site_content.{$section}"), $english, $filled);

        if (! empty($presented['feature_image']) && ! Str::startsWith($presented['feature_image'], ['http://', 'https://'])) {
            $presented['feature_image'] = rtrim(config('app.url'), '/').'/'.ltrim($presented['feature_image'], '/');
        }

        return $presented;
    }

    /**
     * Editing the site copy changes what every visitor reads, so it is kept to
     * the roles that run the business.
     */
    protected function ensureCanEditContent(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasAnyRole(['owner', 'staff'])) {
            abort(403, __('messages.no_content_access'));
        }
    }
}
