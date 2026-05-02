<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerAttribution;
use Illuminate\Http\Request;

class CustomerAttributionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $attributions = CustomerAttribution::with(['customer', 'sourceUser', 'referralCode'])
            ->when($request->has('source_type'), function ($query) use ($request) {
                $query->where('source_type', $request->source_type);
            })
            ->when($request->has('source_user_id'), function ($query) use ($request) {
                $query->where('source_user_id', $request->source_user_id);
            })
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->paginate($request->get('per_page', 15));

        return response()->json($attributions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_user_id' => 'required|unique:customer_attributions,customer_user_id|exists:users,id',
            'source_type' => 'required|in:marketer,staff,secretary,walk_in,customer_referral,organic,campaign',
            'source_user_id' => 'nullable|exists:users,id',
            'referral_code_id' => 'nullable|exists:referral_codes,id',
            'campaign_code' => 'nullable|string|max:255',
            'status' => 'sometimes|in:pending,verified,rejected',
            'notes' => 'nullable|string',
        ]);

        $validated['captured_at'] = now();

        $attribution = CustomerAttribution::create($validated);

        return response()->json([
            'message' => 'Customer attribution created successfully',
            'data' => $attribution->load(['customer', 'sourceUser', 'referralCode'])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(CustomerAttribution $customerAttribution)
    {
        return response()->json([
            'data' => $customerAttribution->load(['customer', 'sourceUser', 'referralCode'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CustomerAttribution $customerAttribution)
    {
        $validated = $request->validate([
            'source_type' => 'sometimes|in:marketer,staff,secretary,walk_in,customer_referral,organic,campaign',
            'source_user_id' => 'nullable|exists:users,id',
            'referral_code_id' => 'nullable|exists:referral_codes,id',
            'campaign_code' => 'nullable|string|max:255',
            'status' => 'sometimes|in:pending,verified,rejected',
            'notes' => 'nullable|string',
        ]);

        $customerAttribution->update($validated);

        return response()->json([
            'message' => 'Customer attribution updated successfully',
            'data' => $customerAttribution->load(['customer', 'sourceUser', 'referralCode'])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CustomerAttribution $customerAttribution)
    {
        $customerAttribution->delete();

        return response()->json([
            'message' => 'Customer attribution deleted successfully'
        ]);
    }

    /**
     * Get attributions by source user.
     */
    public function bySourceUser(Request $request, $sourceUserId)
    {
        $attributions = CustomerAttribution::with(['customer'])
            ->where('source_user_id', $sourceUserId)
            ->paginate($request->get('per_page', 15));

        return response()->json($attributions);
    }
}