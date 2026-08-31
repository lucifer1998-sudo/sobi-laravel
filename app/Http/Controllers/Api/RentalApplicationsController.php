<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\NewRentalApplication;
use App\Models\RentalApplication;
use App\Models\RentalApplicationDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RentalApplicationsController extends Controller
{
    /**
     * Documents and signatures are applicant PII, so they live on the private
     * disk and are only ever served through the gated download route.
     */
    protected const DISK = 'local';

    protected const MAX_DOCUMENT_KILOBYTES = 10240;

    protected const MAX_DOCUMENTS_PER_REQUEST = 10;

    /**
     * The window an applicant has to come back and upload their documents.
     */
    protected const UPLOAD_LINK_HOURS = 48;

    /**
     * Record an application. Open to visitors, since this is what the public
     * wizard posts to.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $application = new RentalApplication($this->applicationAttributes($validated));
        $application->application_id = $this->generateApplicationId();
        $application->signature_path = $this->storeSignature(
            $validated['signature'],
            $application->application_id
        );
        $application->save();

        $this->saveHousehold($application, $validated);

        $this->notifyAdmin($application);

        return response()->json([
            'message' => __('messages.application_received'),
            'application_id' => $application->application_id,
            'document_upload_url' => $this->documentUploadUrl($application),
        ], 201);
    }

    /**
     * Multi-file upload. Reached with a signed link that expires, so an
     * applicant can finish later without an account, but a guessed URL fails.
     */
    public function storeDocuments(Request $request, string $applicationId): JsonResponse
    {
        $application = $this->findApplication($applicationId);

        $request->validate([
            'documents' => ['required', 'array', 'max:'.self::MAX_DOCUMENTS_PER_REQUEST],
            'documents.*' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,pdf',
                'max:'.self::MAX_DOCUMENT_KILOBYTES,
            ],
        ]);

        $saved = [];

        foreach ($request->file('documents') as $file) {
            $path = $file->store("applications/{$application->application_id}/documents", self::DISK);

            $saved[] = $application->documents()->create([
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return response()->json([
            // French counts 0 as singular where English and Spanish do not, so the
            // plural form has to come from the translator rather than a ternary.
            'message' => trans_choice('messages.documents_uploaded', count($saved), ['count' => count($saved)]),
            'documents' => $saved,
        ], 201);
    }

    /**
     * Paginated applications for the admin table, with search and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureCanViewApplications();

        $applications = RentalApplication::query()->withCount('documents');

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $applications->where(function ($query) use ($searchTerm) {
                $query->where('first_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('phone', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('apartment', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('application_id', 'LIKE', "%{$searchTerm}%");
            });
        }

        $sortBy = $request->get('sort_by');
        $sortOrder = in_array($request->get('sort_order'), ['asc', 'desc']) ? $request->get('sort_order') : 'desc';

        if (in_array($sortBy, ['first_name', 'last_name', 'email', 'phone', 'apartment', 'created_at'])) {
            $applications->orderBy($sortBy, $sortOrder);
        } else {
            $applications->orderBy('created_at', 'desc');
        }

        $perPage = min((int) $request->get('per_page', env('PAGINATION_LIMIT', 10)), 100);

        return response()->json($applications->paginate($perPage));
    }

    /**
     * One application. A visitor with the link sees their own receipt; an admin
     * additionally sees the uploaded documents.
     */
    public function show(string $applicationId): JsonResponse
    {
        $application = $this->findApplication($applicationId);
        $application->load(['kids', 'pets', 'incomeSources']);

        if ($this->canViewApplications()) {
            $application->load('documents');
            $application->setAttribute('document_upload_url', $this->documentUploadUrl($application));
        }

        return response()->json($application);
    }

    /**
     * Remove an application. This takes the signature and every uploaded
     * document with it, so it is kept to owners.
     */
    public function destroy(string $applicationId): JsonResponse
    {
        $this->ensureCanDeleteApplications();

        $application = $this->findApplication($applicationId);

        // The rows cascade, but the files on disk do not.
        Storage::disk(self::DISK)->deleteDirectory("applications/{$application->application_id}");

        $application->delete();

        return response()->json(['message' => __('messages.application_deleted')]);
    }

    /**
     * A fresh signed upload link for the admin to send an applicant who never
     * got round to uploading, or whose link expired.
     */
    public function documentUploadLink(string $applicationId): JsonResponse
    {
        $this->ensureCanViewApplications();

        $application = $this->findApplication($applicationId);

        return response()->json([
            'url' => $this->documentUploadUrl($application),
            'expires_in_hours' => self::UPLOAD_LINK_HOURS,
        ]);
    }

    /**
     * Stream one document to an admin. The file has no public URL, so this is
     * the only way to read it.
     */
    public function downloadDocument(string $applicationId, string $documentId): StreamedResponse
    {
        $this->ensureCanViewApplications();

        $application = $this->findApplication($applicationId);

        /** @var RentalApplicationDocument $document */
        $document = $application->documents()->findOrFail($documentId);

        return Storage::disk(self::DISK)->download($document->path, $document->original_name);
    }

    /**
     * Stream the signature image to an admin, on the same terms as a document.
     */
    public function downloadSignature(string $applicationId): StreamedResponse
    {
        $this->ensureCanViewApplications();

        $application = $this->findApplication($applicationId);

        return Storage::disk(self::DISK)->response($application->signature_path);
    }

    protected function rules(): array
    {
        return [
            // The minimum an application is not worth storing without.
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'apartment' => ['required', 'string', 'max:255'],
            // The unit is picked from the listings, so it has to be one that is
            // actually on the market.
            'property_id' => ['nullable', 'string', Rule::exists('properties', 'id')->where('listed', true)],
            'agreed' => ['required', 'accepted'],
            'signature' => ['required', 'string'],

            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'own_rent' => ['nullable', 'in:rent,own'],
            'monthly_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'move_in_date' => ['nullable', 'date'],
            'move_out_date' => ['nullable', 'date'],
            'landlord_name' => ['nullable', 'string', 'max:255'],
            'landlord_phone' => ['nullable', 'string', 'max:30'],

            'is_student' => ['nullable', 'boolean'],
            'school_name' => ['nullable', 'string', 'max:255'],
            'major' => ['nullable', 'string', 'max:255'],
            'enrollment_date' => ['nullable', 'date'],
            'graduation_date' => ['nullable', 'date'],
            'monthly_stipend' => ['nullable', 'numeric', 'min:0', 'max:99999999'],

            'is_employed' => ['nullable', 'boolean'],
            'employer_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'supervisor' => ['nullable', 'string', 'max:255'],
            'work_phone' => ['nullable', 'string', 'max:30'],
            'monthly_income' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'employment_start_date' => ['nullable', 'date'],

            'has_past_employer' => ['nullable', 'boolean'],
            'past_employer_name' => ['nullable', 'string', 'max:255'],
            'past_position' => ['nullable', 'string', 'max:255'],
            'past_supervisor' => ['nullable', 'string', 'max:255'],
            'past_work_phone' => ['nullable', 'string', 'max:30'],
            'past_monthly_income' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'past_start_date' => ['nullable', 'date'],
            'past_end_date' => ['nullable', 'date'],

            'emergency_name' => ['nullable', 'string', 'max:255'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'emergency_relation' => ['nullable', 'string', 'max:255'],
            'desired_move_in' => ['nullable', 'date'],
            'desired_move_out' => ['nullable', 'date'],
            'reason_for_moving' => ['nullable', 'string', 'max:5000'],

            'has_legal_issue' => ['nullable', 'boolean'],
            'legal_explanation' => ['nullable', 'string', 'max:5000'],

            'kids' => ['nullable', 'array', 'max:20'],
            'kids.*.name' => ['required', 'string', 'max:255'],
            'kids.*.age' => ['nullable', 'integer', 'min:0', 'max:17'],

            'pets' => ['nullable', 'array', 'max:20'],
            'pets.*.type' => ['required', 'string', 'max:50'],
            'pets.*.name' => ['required', 'string', 'max:255'],

            'income_sources' => ['nullable', 'array', 'max:20'],
            'income_sources.*.source' => ['required', 'string', 'max:255'],
            'income_sources.*.monthly_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /**
     * Everything from the request that maps straight onto a column. The
     * signature and the repeaters are handled separately.
     */
    protected function applicationAttributes(array $validated): array
    {
        $attributes = collect($validated)
            ->except(['signature', 'agreed', 'kids', 'pets', 'income_sources'])
            ->all();

        $attributes['agreed_at'] = now();

        return $attributes;
    }

    protected function saveHousehold(RentalApplication $application, array $validated): void
    {
        foreach ($validated['kids'] ?? [] as $kid) {
            $application->kids()->create($kid);
        }

        foreach ($validated['pets'] ?? [] as $pet) {
            $application->pets()->create($pet);
        }

        foreach ($validated['income_sources'] ?? [] as $source) {
            $application->incomeSources()->create($source);
        }
    }

    /**
     * The signature pad sends a data URL. Decode it and write a real PNG so the
     * column holds a file path like every other upload in the app.
     */
    protected function storeSignature(string $signature, string $applicationId): string
    {
        $encoded = $signature;

        if (str_starts_with($signature, 'data:')) {
            [, $encoded] = explode(',', $signature, 2);
        }

        $binary = base64_decode($encoded, true);

        if ($binary === false) {
            abort(422, __('messages.signature_unreadable'));
        }

        $path = "applications/{$applicationId}/signature.png";
        Storage::disk(self::DISK)->put($path, $binary);

        return $path;
    }

    /**
     * A signed link to the frontend upload page. The signature is generated over
     * the API route the page posts to, so the page passes the query string
     * straight through and Laravel verifies it there.
     */
    protected function documentUploadUrl(RentalApplication $application): string
    {
        $signedApiUrl = URL::temporarySignedRoute(
            'applications.documents.store',
            now()->addHours(self::UPLOAD_LINK_HOURS),
            ['applicationId' => $application->application_id]
        );

        $query = parse_url($signedApiUrl, PHP_URL_QUERY);

        return rtrim(config('services.frontend.url'), '/')
            .'/rental-application/documents'
            ."?id={$application->application_id}&{$query}";
    }

    /**
     * Short, random, and case-sensitive. Long enough that the receipt link
     * cannot be guessed or walked.
     */
    protected function generateApplicationId(): string
    {
        do {
            $applicationId = Str::random(32);
        } while (RentalApplication::where('application_id', $applicationId)->exists());

        return $applicationId;
    }

    protected function findApplication(string $applicationId): RentalApplication
    {
        return RentalApplication::where('application_id', $applicationId)->firstOrFail();
    }

    /**
     * Tell the admin an application came in. The application is already saved by
     * this point, so a broken mail server must not fail the request.
     */
    protected function notifyAdmin(RentalApplication $application): void
    {
        $adminEmail = config('mail.admin_address');

        if (! $adminEmail) {
            Log::warning('ADMIN_EMAIL is not set, skipping the new application notification.', [
                'application_id' => $application->application_id,
            ]);

            return;
        }

        try {
            Mail::to($adminEmail)->send(new NewRentalApplication($application));
        } catch (Throwable $exception) {
            Log::error('Could not send the new application notification.', [
                'application_id' => $application->application_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Applications hold income, employment and household details, so reading
     * them is limited to the roles that process them.
     */
    protected function canViewApplications(): bool
    {
        return (bool) $this->currentUser()?->hasAnyRole(['owner', 'staff']);
    }

    /**
     * The sanctum guard, not the default one, because show() is a public route:
     * an admin reaching it still has a session, and Auth::user() would not look
     * for one outside the auth middleware.
     */
    protected function currentUser(): ?\App\Models\User
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::guard('sanctum')->user();

        return $user;
    }

    protected function ensureCanViewApplications(): void
    {
        if (! $this->canViewApplications()) {
            abort(403, __('messages.no_application_access'));
        }
    }

    protected function ensureCanDeleteApplications(): void
    {
        $user = $this->currentUser();

        if (! $user || ! $user->hasRole('owner')) {
            abort(403, __('messages.owner_only_delete'));
        }
    }
}
