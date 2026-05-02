<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ReferralEdge;
use Illuminate\Http\Request;

class ReferralEdgeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $edges = ReferralEdge::with(['referrer', 'referred'])
            ->when($request->has('referrer_user_id'), function ($query) use ($request) {
                $query->where('referrer_user_id', $request->referrer_user_id);
            })
            ->when($request->has('referred_user_id'), function ($query) use ($request) {
                $query->where('referred_user_id', $request->referred_user_id);
            })
            ->paginate($request->get('per_page', 15));

        return response()->json($edges);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'referrer_user_id' => 'required|exists:users,id',
            'referred_user_id' => 'required|exists:users,id|different:referrer_user_id',
            'referral_code_id' => 'nullable|exists:referral_codes,id',
        ]);

        $edge = ReferralEdge::create($validated);

        return response()->json([
            'message' => 'Referral edge created successfully',
            'data' => $edge->load(['referrer', 'referred'])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ReferralEdge $referralEdge)
    {
        return response()->json([
            'data' => $referralEdge->load(['referrer', 'referred'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReferralEdge $referralEdge)
    {
        $validated = $request->validate([
            'referral_code_id' => 'nullable|exists:referral_codes,id',
        ]);

        $referralEdge->update($validated);

        return response()->json([
            'message' => 'Referral edge updated successfully',
            'data' => $referralEdge->load(['referrer', 'referred'])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReferralEdge $referralEdge)
    {
        $referralEdge->delete();

        return response()->json([
            'message' => 'Referral edge deleted successfully'
        ]);
    }

    /**
     * Get referrer's referrals.
     */
    public function referrals(Request $request, $userId)
    {
        $referrals = ReferralEdge::with('referred')
            ->where('referrer_user_id', $userId)
            ->paginate($request->get('per_page', 15));

        return response()->json($referrals);
    }

    /**
     * Get user's referrer.
     */
    public function referrer($userId)
    {
        $edge = ReferralEdge::where('referred_user_id', $userId)->first();

        if (!$edge) {
            return response()->json(['message' => 'No referrer found'], 404);
        }

        return response()->json(['data' => $edge->load('referrer')]);
    }
}