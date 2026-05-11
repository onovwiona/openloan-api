<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class KycController extends Controller
{
    /**
     * Customer uploads KYC documents
     * POST /kyc/upload
     */
    public function upload(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->customerProfile;

        if (!$profile) {
            $profile = CustomerProfile::create([
                'user_id' => $user->id,
                'kyc_status' => 'pending',
            ]);
        } else {
            Gate::authorize('update', $profile);
        }

        $validator = validator($request->all(), [
            'documents' => 'required|array|min:1',
            'documents.*.type' => 'required|string|in:id_card,passport,driver_license,utility_bill',
            'documents.*.file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please check the required format.',
                'errors' => $validator->errors(),
                'expected_format' => [
                    'content_type' => 'multipart/form-data',
                    'documents' => [
                        [
                            'type' => 'id_card (or: passport, driver_license, utility_bill)',
                            'file' => 'file upload (jpg, jpeg, png, pdf - max 5MB)'
                        ],
                        [
                            'type' => 'passport',
                            'file' => 'file upload (jpg, jpeg, png, pdf - max 5MB)'
                        ]
                    ],
                    'example_curl' => 'curl -X POST "{{base_url}}/api/v1/user/kyc" -H "Authorization: Bearer TOKEN" -F "documents[0][type]=id_card" -F "documents[0][file]=@/path/to/id_card.jpg" -F "documents[1][type]=passport" -F "documents[1][file]=@/path/to/passport.pdf"'
                ],
                'supported_document_types' => ['id_card', 'passport', 'driver_license', 'utility_bill'],
                'supported_file_formats' => ['jpg', 'jpeg', 'png', 'pdf'],
                'max_file_size' => '5MB per file'
            ], 422);
        }

        $result = $this->prepareKycUploads($request, $profile);
        if (!empty($result['errors'])) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please fix the upload data.',
                'errors' => $result['errors'],
            ], 422);
        }

        $uploadedDocs = $result['uploads'];

        $profile->update([
            'kyc_documents' => array_merge($profile->kyc_documents ?? [], $uploadedDocs),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'KYC documents uploaded successfully',
            'data' => ['kyc_documents' => $profile->fresh()->kyc_documents],
        ], 201);
    }

    /**
     * Prepare KYC uploads and validate missing files/duplicate types
     */
    private function prepareKycUploads(Request $request, CustomerProfile $profile): array
    {
        $documents = $request->input('documents', []);
        $existingTypes = collect($profile->kyc_documents ?? [])->pluck('type')->filter()->map(fn ($type) => strtolower($type))->all();
        $seenTypes = [];
        $uploads = [];
        $errors = [];

        foreach ($documents as $index => $docData) {
            $type = isset($docData['type']) ? $this->normalizeDocumentType($docData['type']) : null;
            $file = $request->file("documents.{$index}.file");

            if (empty($type)) {
                $errors["documents.{$index}.type"][] = 'The document type is required.';
                continue;
            }

            if (!$file) {
                $errors["documents.{$index}.file"][] = 'The file is required for this document entry.';
                continue;
            }

            if (in_array($type, $existingTypes)) {
                $errors["documents.{$index}.type"][] = "The document type '{$type}' has already been uploaded.";
                continue;
            }

            if (in_array($type, $seenTypes)) {
                $errors["documents.{$index}.type"][] = "The document type '{$type}' is duplicated in the request.";
                continue;
            }

            $seenTypes[] = $type;
            $path = $file->store('kyc/' . $profile->user_id, 'public');

            $uploads[] = [
                'type' => $type,
                'url' => Storage::url($path),
                'filename' => $file->getClientOriginalName(),
                'status' => 'pending',
                'verified_at' => null,
                'verified_by' => null,
                'uploaded_at' => now()->toISOString(),
            ];
        }

        return ['uploads' => $uploads, 'errors' => $errors];
    }

    public function myKyc(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->customerProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Customer profile not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'phone']),
                'profile' => $profile,
                'kyc_documents' => $profile->kyc_documents ?? [],
                'is_verified' => $profile->kyc_status === 'verified',
            ],
        ]);
    }

    /**
     * Customer uploads KYC documents for a specified user
     * POST /users/{user}/kyc
     */
    public function uploadForUser(Request $request, User $user): JsonResponse
    {
        $profile = $user->customerProfile;

        if (!$profile) {
            if (Auth::id() !== $user->id) {
                abort(403, 'Not authorized to upload KYC for this user');
            }

            $profile = CustomerProfile::create([
                'user_id' => $user->id,
                'kyc_status' => 'pending',
            ]);
        } else {
            Gate::authorize('update', $profile);
        }

        $validator = validator($request->all(), [
            'documents' => 'required|array|min:1',
            'documents.*.type' => 'required|string|in:id_card,passport,driver_license,utility_bill',
            'documents.*.file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $result = $this->prepareKycUploads($request, $profile);
        if (!empty($result['errors'])) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please fix the upload data.',
                'errors' => $result['errors'],
            ], 422);
        }

        $uploadedDocs = $result['uploads'];

        $profile->update([
            'kyc_documents' => array_merge($profile->kyc_documents ?? [], $uploadedDocs),
            'kyc_status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'KYC documents uploaded successfully',
            'data' => ['kyc_documents' => $profile->fresh()->kyc_documents],
        ], 201);
    }

    public function uploadPassportPhoto(Request $request): JsonResponse
    {
        return $this->uploadSingleDocument($request, 'passport');
    }

    public function uploadGuarantorForm(Request $request): JsonResponse
    {
        // This endpoint is now deprecated. Use loan application documents endpoint instead.
        // Kept for backward compatibility if needed.
        abort(410, 'This endpoint is deprecated. Use loan application documents instead.');
    }

    private function uploadSingleDocument(Request $request, string $type): JsonResponse
    {
        $user = $request->user();
        $profile = $user->customerProfile;

        if (!$profile) {
            $profile = CustomerProfile::create([
                'user_id' => $user->id,
                'kyc_status' => 'pending',
            ]);
        } else {
            Gate::authorize('update', $profile);
        }

        $validator = validator($request->all(), [
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $normalizedType = $this->normalizeDocumentType($type);
        $existingTypes = collect($profile->kyc_documents ?? [])->pluck('type')->filter()->map(fn ($type) => strtolower($type))->all();

        if (in_array($normalizedType, $existingTypes)) {
            return response()->json([
                'success' => false,
                'message' => "The document type '{$normalizedType}' has already been uploaded.",
            ], 422);
        }

        $file = $request->file('file');
        $path = $file->store('kyc/' . $profile->user_id, 'public');
        $document = [
            'type' => $normalizedType,
            'url' => Storage::url($path),
            'filename' => $file->getClientOriginalName(),
            'status' => 'pending',
            'verified_at' => null,
            'verified_by' => null,
            'uploaded_at' => now()->toISOString(),
        ];

        $profile->update([
            'kyc_documents' => array_merge($profile->kyc_documents ?? [], [$document]),
            'kyc_status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'KYC document uploaded successfully',
            'data' => ['kyc_documents' => $profile->fresh()->kyc_documents],
        ], 201);
    }

    private function normalizeDocumentType(string $type): string
    {
        return match (strtolower($type)) {
            'passport_photo' => 'passport',
            default => strtolower($type),
        };
    }

    /**
     * List KYC profiles for admin/staff/secretary/marketer
     * GET /kyc
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CustomerProfile::class);

        $profiles = CustomerProfile::with('user')
            ->when($request->has('kyc_status'), fn($q) => $q->where('kyc_status', $request->kyc_status))
            ->when($request->has('user_id'), fn($q) => $q->where('user_id', $request->user_id))
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $profiles]);
    }

    /**
     * Show a specific KYC profile
     * GET /kyc/{profile}
     */
    public function showProfile(CustomerProfile $profile): JsonResponse
    {
        Gate::authorize('view', $profile);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $profile->user->only(['id', 'first_name', 'last_name', 'email', 'phone']),
                'profile' => $profile,
                'kyc_documents' => $profile->kyc_documents ?? [],
            ],
        ]);
    }

    /**
     * Admin only update a KYC profile
     * PATCH /kyc/{profile}
     */
    public function updateProfile(Request $request, CustomerProfile $profile): JsonResponse
    {
        Gate::authorize('update', $profile);

        $validator = validator($request->all(), [
            'kyc_status' => 'sometimes|in:pending,verified,rejected',
            'kyc_documents' => 'nullable|array',
            'verification_notes' => 'nullable|string|max:1000',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $profile->update($validator->validated());

        return response()->json(['success' => true, 'message' => 'KYC profile updated successfully', 'data' => $profile->fresh()]);
    }

    /**
     * Admin only delete a KYC profile
     * DELETE /kyc/{profile}
     */
    public function destroyProfile(CustomerProfile $profile): JsonResponse
    {
        Gate::authorize('delete', $profile);

        $profile->delete();

        return response()->json(['success' => true, 'message' => 'KYC profile deleted successfully']);
    }

    /**
     * Staff/Admin view customer KYC
     * GET /customers/{user_id}/kyc
     */
    public function show(User $user): JsonResponse
    {
        Gate::authorize('view', $user->customerProfile);

        $profile = $user->customerProfile;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'phone']),
                'profile' => $profile,
                'kyc_documents' => $profile->kyc_documents ?? [],
            ],
        ]);
    }

    /**
     * Admin/Staff verify KYC
     * PATCH /kyc/{profile_id}/verify
     */
    public function verify(Request $request, CustomerProfile $profile): JsonResponse
    {
        Gate::authorize('verifyKyc', $profile);

        $validator = validator($request->all(), [
            'verified_types' => 'required|array|min:1',
            'verified_types.*' => 'required|string|in:id_card,passport,driver_license,utility_bill,bank_statement,proof_income',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $verifiedTypes = collect($validator->validated()['verified_types'])->map(fn ($type) => strtolower($type))->unique()->all();
        $documents = collect($profile->kyc_documents ?? [])->map(function ($doc) use ($verifiedTypes) {
            if (in_array(strtolower($doc['type']), $verifiedTypes, true)) {
                $doc['status'] = 'verified';
                $doc['verified_at'] = now()->toISOString();
                $doc['verified_by'] = auth()->id();
            }
            return $doc;
        })->all();

        $profile->update([
            'kyc_documents' => $documents,
            'kyc_reviewed_by' => auth()->id(),
            'verification_notes' => $request->notes,
            'kyc_status' => $this->determineKycStatus($documents),
            'kyc_verified_at' => $this->determineKycStatus($documents) === 'verified' ? now() : $profile->kyc_verified_at,
        ]);

        Log::info('KYC verified by user: ' . auth()->id() . ' for profile: ' . $profile->id);

        $profile->refresh();
        $documents = $profile->kyc_documents ?? [];

        return response()->json([
            'success' => true,
            'message' => 'KYC verified successfully',
            'data' => [
                'profile' => $profile,
                'kyc_documents' => $documents,
                'document_statuses' => $this->buildDocumentVerificationSummary($documents),
            ],
        ]);
    }

    private function determineKycStatus(array $documents): string
    {
        $coreTypes = ['id_card', 'passport', 'driver_license', 'utility_bill'];

        $coreDocs = collect($documents)->filter(fn ($doc) => in_array(strtolower($doc['type']), $coreTypes, true));

        if ($coreDocs->isEmpty()) {
            return 'pending';
        }

        return $coreDocs->every(fn ($doc) => isset($doc['status']) && $doc['status'] === 'verified') ? 'verified' : 'pending';
    }

    private function buildDocumentVerificationSummary(array $documents): array
    {
        return collect($documents)->mapWithKeys(function ($doc) {
            return [strtolower($doc['type']) => $doc['status'] ?? 'pending'];
        })->all();
    }

    /**
     * Admin only reject KYC
     * PATCH /kyc/{profile_id}/reject
     */
    public function verifyDocument(Request $request, CustomerProfile $profile, string $documentType): JsonResponse
    {
        Gate::authorize('verifyKyc', $profile);

        $normalizedType = $this->normalizeDocumentType($documentType);
        $allowedTypes = ['id_card', 'passport', 'driver_license', 'utility_bill', 'bank_statement', 'proof_income', 'guarantor_form'];

        if (!in_array($normalizedType, $allowedTypes, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported document type.',
                'supported_document_types' => $allowedTypes,
            ], 422);
        }

        $documents = collect($profile->kyc_documents ?? []);
        $found = false;

        $updatedDocuments = $documents->map(function ($doc) use ($normalizedType, &$found) {
            if (strtolower($doc['type']) === $normalizedType) {
                $found = true;
                $doc['status'] = 'verified';
                $doc['verified_at'] = now()->toISOString();
                $doc['verified_by'] = auth()->id();
            }
            return $doc;
        })->all();

        if (!$found) {
            return response()->json([
                'success' => false,
                'message' => "Document type '{$normalizedType}' not found for this profile.",
            ], 404);
        }

        $profile->update([
            'kyc_documents' => $updatedDocuments,
            'kyc_reviewed_by' => auth()->id(),
            'verification_notes' => $request->input('notes'),
            'kyc_status' => $this->determineKycStatus($updatedDocuments),
            'kyc_verified_at' => $this->determineKycStatus($updatedDocuments) === 'verified' ? now() : $profile->kyc_verified_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document verified successfully',
            'data' => [
                'document_type' => $normalizedType,
                'profile' => $profile->fresh(),
                'kyc_documents' => $profile->fresh()->kyc_documents,
            ],
        ]);
    }

    public function reject(Request $request, CustomerProfile $profile): JsonResponse
    {
        Gate::authorize('rejectKyc', $profile);

        $validator = validator($request->all(), [
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $profile->update([
            'kyc_status' => 'rejected',
            'kyc_verified_at' => null,
            'rejection_reason' => $validator->validated()['reason'],
            'verification_notes' => $validator->validated()['notes'] ?? null,
        ]);

        Log::info('KYC rejected for user: ' . $profile->user_id . ', reason: ' . $request->reason);

        $profile->refresh();
        $documents = $profile->kyc_documents ?? [];

        return response()->json([
            'success' => true,
            'message' => 'KYC rejected',
            'data' => [
                'profile' => $profile,
                'kyc_documents' => $documents,
                'document_statuses' => $this->buildDocumentVerificationSummary($documents),
            ],
        ]);
    }
}


