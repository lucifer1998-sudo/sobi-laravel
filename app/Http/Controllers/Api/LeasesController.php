<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Dropbox\Sign\Api\SignatureRequestApi;
use Dropbox\Sign\ApiException;
use Dropbox\Sign\Configuration;
use Dropbox\Sign\Model\SignatureRequestResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LeasesController extends Controller
{
    /**
     * Leases live in Dropbox Sign, not in our database, so this page is a thin
     * wrapper over their Signature Request list. The payload is shaped like a
     * Laravel paginator so the frontend DataTable can read it unchanged.
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureCanViewLeases();

        $page = max((int) $request->get('page', 1), 1);
        // Dropbox Sign caps a page at 100.
        $perPage = min((int) $request->get('per_page', 10), 100);

        try {
            $response = $this->signatureRequests()->signatureRequestList(
                null,
                $page,
                $perPage,
                $request->get('search') ?: null,
            );
        } catch (ApiException $exception) {
            // Dropbox Sign rejects some search strings, such as an unbalanced
            // "a:b:c". That is the visitor mistyping, not an outage, so the table
            // shows an empty result rather than an error.
            if ($exception->getCode() === 400) {
                Log::warning('Dropbox Sign rejected the lease search.', [
                    'search' => $request->get('search'),
                ]);

                return response()->json($this->emptyPage($perPage));
            }

            Log::error('Could not list signature requests from Dropbox Sign.', [
                'error' => $exception->getMessage(),
                'response' => (string) $exception->getResponseBody(),
            ]);

            return response()->json(['message' => 'Could not load leases from Dropbox Sign.'], 502);
        }

        $leases = [];

        foreach ($response->getSignatureRequests() as $signatureRequest) {
            $leases[] = $this->formatLease($signatureRequest);
        }

        $listInfo = $response->getListInfo();

        return response()->json([
            'data' => $leases,
            'current_page' => $listInfo->getPage(),
            // Dropbox Sign reports 0 pages when nothing matched, which reads as a
            // broken paginator on the frontend.
            'last_page' => max($listInfo->getNumPages(), 1),
            'per_page' => $listInfo->getPageSize(),
            'total' => $listInfo->getNumResults(),
        ]);
    }

    /**
     * Send the viewer to the signed document. Dropbox Sign hands back a short
     * lived URL on their storage, so we redirect rather than proxy the file.
     */
    public function show(string $signatureId): RedirectResponse|JsonResponse
    {
        $this->ensureCanViewLeases();

        try {
            // force_download 0 so the PDF opens in the browser instead of saving.
            $file = $this->signatureRequests()->signatureRequestFilesAsFileUrl($signatureId, 0);
        } catch (ApiException $exception) {
            Log::error('Could not fetch the signed file from Dropbox Sign.', [
                'signature_request_id' => $signatureId,
                'error' => $exception->getMessage(),
                'response' => (string) $exception->getResponseBody(),
            ]);

            return response()->json(['message' => 'Could not open this lease.'], 502);
        }

        return redirect()->away($file->getFileUrl());
    }

    /**
     * Only the fields the table needs. Whoever has not signed, and whoever turned
     * the request down, are both listed so the frontend can name them in the
     * status tooltip.
     */
    protected function formatLease(SignatureRequestResponse $signatureRequest): array
    {
        $pendingSigners = [];
        $declinedBy = [];

        foreach ($signatureRequest->getSignatures() ?? [] as $signature) {
            $signerName = $signature->getSignerName() ?: $signature->getSignerEmailAddress();

            if ($signature->getStatusCode() === 'declined') {
                $declinedBy[] = [
                    'name' => $signerName,
                    'reason' => $signature->getDeclineReason(),
                ];

                continue;
            }

            if ($signature->getStatusCode() !== 'signed') {
                $pendingSigners[] = [
                    'name' => $signerName,
                    'email' => $signature->getSignerEmailAddress(),
                ];
            }
        }

        $createdAt = $signatureRequest->getCreatedAt();

        return [
            'id' => $signatureRequest->getSignatureRequestId(),
            'title' => $signatureRequest->getTitle() ?: $signatureRequest->getSubject(),
            'status' => $this->statusFor($signatureRequest),
            'pending_signers' => $pendingSigners,
            'declined_by' => $declinedBy,
            'created_at' => $createdAt ? Carbon::createFromTimestamp($createdAt)->toIso8601String() : null,
        ];
    }

    /**
     * Dropbox Sign reports state as three separate flags. A request that is done
     * is complete; one that somebody turned down is declined; one their side could
     * not process has an error. Anything else is still waiting on a signature.
     */
    protected function statusFor(SignatureRequestResponse $signatureRequest): string
    {
        if ($signatureRequest->getIsComplete()) {
            return 'complete';
        }

        if ($signatureRequest->getIsDeclined()) {
            return 'declined';
        }

        if ($signatureRequest->getHasError()) {
            return 'error';
        }

        return 'pending';
    }

    protected function emptyPage(int $perPage): array
    {
        return [
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
        ];
    }

    protected function signatureRequests(): SignatureRequestApi
    {
        $apiKey = config('services.dropbox_sign.api_key');

        if (! $apiKey) {
            abort(500, 'Dropbox Sign is not configured.');
        }

        // A fresh Configuration each call. The SDK's default one is a shared
        // singleton, so setting the key on it would leak across requests.
        return new SignatureRequestApi((new Configuration)->setUsername($apiKey));
    }

    /**
     * Signed leases carry tenant details, so they are kept to the roles that
     * handle paperwork.
     */
    protected function ensureCanViewLeases(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasAnyRole(['owner', 'staff'])) {
            abort(403, 'You do not have access to leases.');
        }
    }
}
