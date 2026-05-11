<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class CustomerProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $profiles = CustomerProfile::with('user')
            ->when($request->has('kyc_status'), function ($query) use ($request) {
                $query->where('kyc_status', $request->kyc_status);
            })
            ->when($request->has('employment_status'), function ($query) use ($request) {
                $query->where('employment_status', $request->employment_status);
            })
            ->paginate($request->get('per_page', 15));

        return response()->json($profiles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|unique:customer_profiles,user_id|exists:users,id',
            'address' => 'nullable|string|max:500',
            'dob' => 'nullable|date',
            'employment_status' => 'nullable|string|max:100',
            'monthly_income' => 'nullable|numeric|min:0',
        ]);

        $profile = CustomerProfile::create(array_merge($validated, [
            'kyc_status' => 'pending',
        ]));

        return response()->json([
            'message' => 'Customer profile created successfully',
            'data' => $profile->load('user')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(CustomerProfile $customerProfile)
    {
        return response()->json([
            'data' => $customerProfile->load('user')
        ]);
    }

    /**
     * Display the authenticated customer's profile.
     */
    public function myProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->customerProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Customer profile not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $profile]);
    }

    /**
     * Update the authenticated customer's profile.
     */
    public function updateMyProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->customerProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Customer profile not found'], 404);
        }

        $validated = $request->validate([
            'address' => 'nullable|string|max:500',
            'dob' => 'nullable|date',
            'bvn' => 'nullable|string|max:11',
            'nin' => 'nullable|string|max:11',
            'employment_status' => 'nullable|string|max:100',
            'monthly_income' => 'nullable|numeric|min:0',
        ]);

        // Handle BVN encryption if provided
        if (!empty($validated['bvn'])) {
            $bvnClean = preg_replace('/\s+/', '', strtoupper($validated['bvn']));
            $validated['bvn_encrypted'] = Crypt::encryptString($bvnClean);
            $validated['bvn_hash'] = hash('sha256', $bvnClean);
            unset($validated['bvn']);
        }

        $profile->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer profile updated successfully',
            'data' => $profile,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CustomerProfile $customerProfile)
    {
        $validated = $request->validate([
            'address' => 'nullable|string|max:500',
            'dob' => 'nullable|date',
            'employment_status' => 'nullable|string|max:100',
            'monthly_income' => 'nullable|numeric|min:0',
        ]);

        $customerProfile->update($validated);

        return response()->json([
            'message' => 'Customer profile updated successfully',
            'data' => $customerProfile->load('user')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CustomerProfile $customerProfile)
    {
        $customerProfile->delete();

        return response()->json([
            'message' => 'Customer profile deleted successfully'
        ]);
    }

    /**
     * Admin/staff only: change customer KYC status.
     */
    public function changeKycStatus(Request $request, CustomerProfile $customerProfile)
    {
        $this->authorize('changeKycStatus', $customerProfile);

        $validated = $request->validate([
            'kyc_status' => 'required|in:pending,verified,rejected',
            'verification_notes' => 'nullable|string|max:1000',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        if ($validated['kyc_status'] === 'verified') {
            $validated['kyc_verified_at'] = now();
            $validated['rejection_reason'] = null;
        }

        if ($validated['kyc_status'] === 'rejected') {
            $validated['kyc_verified_at'] = null;
            $validated['verification_notes'] = $validated['verification_notes'] ?? null;
        }

        $customerProfile->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'KYC status updated successfully',
            'data' => $customerProfile->fresh(),
        ]);
    }
}