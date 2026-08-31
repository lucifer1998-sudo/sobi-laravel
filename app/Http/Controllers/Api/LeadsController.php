<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\NewLead;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class LeadsController extends Controller
{
    /**
     * Record an enquiry. Open to visitors, since this is what the public forms post to.
     * The caller says where it came from, e.g. "contact".
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:5000'],
            'source' => ['required', 'string', 'max:64'],
        ]);

        $lead = Lead::create($validated);

        $this->notifyAdmin($lead);

        return response()->json([
            'message' => __('messages.lead_received'),
            'lead' => $lead,
        ], 201);
    }

    /**
     * Paginated leads for the admin table, with search and a source filter.
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureCanViewLeads();

        $leads = Lead::query();

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $leads->where(function ($query) use ($searchTerm) {
                $query->where('firstname', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('lastname', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('phone', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('message', 'LIKE', "%{$searchTerm}%");
            });
        }

        // The filter is multi select, so the source arrives as "contact,newsletter".
        if ($request->filled('source')) {
            $sources = array_filter(explode(',', $request->source));

            if (! empty($sources)) {
                $leads->whereIn('source', $sources);
            }
        }

        $sortBy = $request->get('sort_by');
        $sortOrder = in_array($request->get('sort_order'), ['asc', 'desc']) ? $request->get('sort_order') : 'desc';

        if (in_array($sortBy, ['firstname', 'lastname', 'email', 'phone', 'source', 'created_at'])) {
            $leads->orderBy($sortBy, $sortOrder);
        } else {
            $leads->orderBy('created_at', 'desc');
        }

        $perPage = min((int) $request->get('per_page', env('PAGINATION_LIMIT', 10)), 100);

        return response()->json($leads->paginate($perPage));
    }

    /**
     * The sources that actually exist, so the filter never offers an empty option.
     */
    public function getSources(): JsonResponse
    {
        $this->ensureCanViewLeads();

        $sources = Lead::query()
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->distinct()
            ->orderBy('source')
            ->pluck('source');

        return response()->json($sources);
    }

    /**
     * Remove a lead. Deleting is permanent, so it is kept to owners.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->ensureCanDeleteLeads();

        Lead::findOrFail($id)->delete();

        return response()->json(['message' => 'Lead deleted successfully.']);
    }

    /**
     * Tell the admin a lead came in. The lead is already saved by this point, so a
     * broken mail server must not fail the request. Log the problem and move on.
     */
    protected function notifyAdmin(Lead $lead): void
    {
        $adminEmail = config('mail.admin_address');

        if (! $adminEmail) {
            Log::warning('ADMIN_EMAIL is not set, skipping the new lead notification.', ['lead_id' => $lead->id]);

            return;
        }

        try {
            Mail::to($adminEmail)->send(new NewLead($lead));
        } catch (Throwable $exception) {
            Log::error('Could not send the new lead notification.', [
                'lead_id' => $lead->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Leads hold customer contact details, so reading them is limited to the
     * roles that handle enquiries.
     */
    protected function ensureCanViewLeads(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasAnyRole(['owner', 'staff'])) {
            abort(403, 'You do not have access to leads.');
        }
    }

    protected function ensureCanDeleteLeads(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasRole('owner')) {
            abort(403, 'Only an owner can delete a lead.');
        }
    }
}
