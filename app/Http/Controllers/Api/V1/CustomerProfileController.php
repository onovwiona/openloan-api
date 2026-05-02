<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Http\Request;

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
            'kyc_status' => 'sometimes|in:pending,verified,rejected',
        ]);

        $profile = CustomerProfile::create($validated);

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
     * Update the specified resource in storage.
     */
    public function update(Request $request, CustomerProfile $customerProfile)
    {
        $validated = $request->validate([
            'address' => 'nullable|string|max:500',
            'dob' => 'nullable|date',
            'employment_status' => 'nullable|string|max:100',
            'monthly_income' => 'nullable|numeric|min:0',
            'kyc_status' => 'sometimes|in:pending,verified,rejected',
        ]);

        if ($validated['kyc_status'] === 'verified' && !$customerProfile->kyc_verified_at) {
            $validated['kyc_verified_at'] = now();
        }

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
}