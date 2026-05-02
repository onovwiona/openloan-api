<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ReferralCode;
use Illuminate\Http\Request;

class ReferralCodeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $codes = ReferralCode::with('user')
            ->when($request->has('code'), function ($query) use ($request) {
                $query->where('code', 'like', "%{$request->code}%");
            })
            ->when($request->has('is_active'), function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->paginate($request->get('per_page', 15));

        return response()->json($codes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'code' => 'required|string|unique:referral_codes,code|max:255',
            'is_active' => 'boolean',
            'expires_at' => 'nullable|date',
        ]);

        $code = ReferralCode::create($validated);

        return response()->json([
            'message' => 'Referral code created successfully',
            'data' => $code->load('user')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ReferralCode $referralCode)
    {
        return response()->json([
            'data' => $referralCode->load('user')
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReferralCode $referralCode)
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|unique:referral_codes,code,' . $referralCode->id . '|max:255',
            'is_active' => 'sometimes|boolean',
            'expires_at' => 'nullable|date',
        ]);

        $referralCode->update($validated);

        return response()->json([
            'message' => 'Referral code updated successfully',
            'data' => $referralCode->load('user')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReferralCode $referralCode)
    {
        $referralCode->delete();

        return response()->json([
            'message' => 'Referral code deleted successfully'
        ]);
    }

    /**
     * Validate a referral code.
     */
    public function validate(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $referralCode = ReferralCode::where('code', $request->code)
            ->where('is_active', true)
            ->first();

        if (!$referralCode) {
            return response()->json(['valid' => false, 'message' => 'Invalid or inactive referral code'], 404);
        }

        if ($referralCode->expires_at && $referralCode->expires_at->isPast()) {
            return response()->json(['valid' => false, 'message' => 'Referral code has expired'], 404);
        }

        return response()->json([
            'valid' => true,
            'data' => $referralCode->load('user')
        ]);
    }
}