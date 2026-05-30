<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
                $query->whereHas('employmentProfile', fn($q) => $q->where('employment_status', $request->employment_status));
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

        $employmentStatus = $validated['employment_status'] ?? null;
        unset($validated['employment_status']);

        $profile = CustomerProfile::create(array_merge($validated, [
            'kyc_status' => 'pending',
        ]));

        if ($employmentStatus !== null) {
            $profile->employmentProfile()->create([
                'employment_status' => $employmentStatus,
                'employment_profile_status' => 'pending',
            ]);
        }

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

        if ($profile->kyc_status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Customer profile cannot be changed after KYC verification.',
                'errors' => ['kyc_status' => ['Profile data is locked after verification.']],
            ], 422);
        }

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:25|unique:users,phone,' . $user->id,
            'address' => 'nullable|string|max:500',
            'dob' => 'nullable|date',
            'bvn' => 'nullable|string|max:11',
            'nin' => 'nullable|string|max:11',
            'employment_status' => 'nullable|string|max:100',
            'monthly_income' => 'nullable|numeric|min:0',
        ]);

        $employmentStatus = $validated['employment_status'] ?? null;
        $userUpdates = Arr::only($validated, ['first_name', 'last_name', 'email', 'phone']);
        $profileUpdates = Arr::except($validated, ['first_name', 'last_name', 'email', 'phone', 'employment_status']);

        if (isset($userUpdates['email'])) {
            if ($user->email_verified_at && $userUpdates['email'] !== $user->email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verified email can only be changed by admin.',
                    'errors' => ['email' => ['Email is already verified and cannot be changed.']],
                ], 422);
            }

            if ($userUpdates['email'] !== $user->email) {
                $userUpdates['email_verified_at'] = null;
            }
        }

        if (isset($userUpdates['phone'])) {
            if ($user->phone_verified_at && $userUpdates['phone'] !== $user->phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verified phone can only be changed by admin.',
                    'errors' => ['phone' => ['Phone is already verified and cannot be changed.']],
                ], 422);
            }

            if ($userUpdates['phone'] !== $user->phone) {
                $userUpdates['phone_verified_at'] = null;
            }
        }

        if (!empty($userUpdates)) {
            $user->forceFill($userUpdates)->save();
        }

        if (!empty($profileUpdates['bvn'])) {
            $bvnClean = preg_replace('/\s+/', '', strtoupper($profileUpdates['bvn']));
            $profileUpdates['bvn_encrypted'] = Crypt::encryptString($bvnClean);
            $profileUpdates['bvn_hash'] = hash('sha256', $bvnClean);
            unset($profileUpdates['bvn']);
        }

        if (!empty($profileUpdates)) {
            $profile->update($profileUpdates);
        }

        if ($employmentStatus !== null) {
            $profile->employmentProfile()->updateOrCreate(
                ['customer_profile_id' => $profile->id],
                ['employment_status' => $employmentStatus, 'employment_profile_status' => 'pending']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Customer profile updated successfully',
            'data' => $profile->fresh()->load('user'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CustomerProfile $customerProfile)
    {
        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $customerProfile->user->id,
            'phone' => 'nullable|string|max:25|unique:users,phone,' . $customerProfile->user->id,
            'address' => 'nullable|string|max:500',
            'dob' => 'nullable|date',
            'bvn' => 'nullable|string|max:11',
            'nin' => 'nullable|string|max:11',
            'employment_status' => 'nullable|string|max:100',
            'monthly_income' => 'nullable|numeric|min:0',
            'update_reason' => 'nullable|string|max:1000',
        ]);

        $employmentStatus = $validated['employment_status'] ?? null;
        $userUpdates = Arr::only($validated, ['first_name', 'last_name', 'email', 'phone']);
        $profileUpdates = Arr::except($validated, ['first_name', 'last_name', 'email', 'phone', 'employment_status']);

        if (!empty($profileUpdates['bvn'])) {
            $bvnClean = preg_replace('/\s+/', '', strtoupper($profileUpdates['bvn']));
            $profileUpdates['bvn_encrypted'] = Crypt::encryptString($bvnClean);
            $profileUpdates['bvn_hash'] = hash('sha256', $bvnClean);
            unset($profileUpdates['bvn']);
        }

        $customer = $customerProfile->user;

        if (isset($userUpdates['email']) && $userUpdates['email'] !== $customer->email) {
            $userUpdates['email_verified_at'] = null;
        }

        if (isset($userUpdates['phone']) && $userUpdates['phone'] !== $customer->phone) {
            $userUpdates['phone_verified_at'] = null;
        }

        if ($customerProfile->kyc_status === 'verified' && (!empty($userUpdates) || !empty($profileUpdates))) {
            $profileUpdates['kyc_status'] = 'pending';
            $profileUpdates['kyc_verified_at'] = null;
            $profileUpdates['kyc_reviewed_by'] = null;
        }

        if (!empty($validated['update_reason'])) {
            $profileUpdates['profile_update_note'] = $validated['update_reason'];
            $profileUpdates['profile_updated_by'] = $request->user()->id;
        }

        if (!empty($userUpdates)) {
            $customer->forceFill($userUpdates)->save();
        }

        if (!empty($profileUpdates)) {
            $customerProfile->update($profileUpdates);
        }

        if ($employmentStatus !== null) {
            $customerProfile->employmentProfile()->updateOrCreate(
                ['customer_profile_id' => $customerProfile->id],
                ['employment_status' => $employmentStatus, 'employment_profile_status' => 'pending']
            );
        }

        return response()->json([
            'message' => 'Customer profile updated successfully',
            'data' => $customerProfile->fresh()->load('user')
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
            'kyc_tier' => 'nullable|integer|min:1|max:3',
            'verification_notes' => 'nullable|string|max:1000',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        if ($validated['kyc_status'] === 'verified') {
            if (isset($validated['kyc_tier']) && in_array($validated['kyc_tier'], [2, 3], true) && ! $this->hasApprovedGovernmentIdOrVerifiedEmploymentProfile($customerProfile)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot assign KYC tier 2 or 3 without approved documentation or verified employment profile.',
                    'errors' => [
                        'kyc_tier' => ['Tier 2+ requires either approved government-issued ID documentation or a verified employment profile.'],
                    ],
                ], 422);
            }

            if (!isset($validated['kyc_tier']) && in_array($customerProfile->kyc_tier, [2, 3], true) && ! $this->hasApprovedGovernmentIdOrVerifiedEmploymentProfile($customerProfile)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot verify KYC at tier 2 or 3 without approved documentation or verified employment profile.',
                    'errors' => [
                        'kyc_tier' => ['Existing tier 2+ requires either approved government-issued ID documentation or a verified employment profile.'],
                    ],
                ], 422);
            }

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

    private function hasApprovedGovernmentId(CustomerProfile $customerProfile): bool
    {
        $approvedTypes = $customerProfile->kycDocuments()
            ->where('verification_status', KycDocument::VERIFICATION_APPROVED)
            ->pluck('document_type')
            ->map(fn ($type) => strtoupper($type))
            ->unique()
            ->all();

        $hasIdCard = in_array(KycDocument::TYPE_ID_CARD_FRONT, $approvedTypes, true)
            && in_array(KycDocument::TYPE_ID_CARD_BACK, $approvedTypes, true);

        $hasPassport = in_array(KycDocument::TYPE_PASSPORT_PHOTO, $approvedTypes, true)
            && in_array(KycDocument::TYPE_PASSPORT_DOCUMENT, $approvedTypes, true);

        $hasLegacyPassport = in_array(KycDocument::TYPE_PASSPORT, $approvedTypes, true);

        return $hasIdCard || $hasPassport || $hasLegacyPassport;
    }

    private function hasApprovedGovernmentIdOrVerifiedEmploymentProfile(CustomerProfile $customerProfile): bool
    {
        if ($customerProfile->employmentProfile?->employment_profile_status === 'verified') {
            return true;
        }

        return $this->hasApprovedGovernmentId($customerProfile);
    }
}
