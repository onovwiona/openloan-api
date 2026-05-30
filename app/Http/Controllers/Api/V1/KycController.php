<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerEmploymentProfile;
use App\Models\CustomerProfile;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        $validation = $this->validateKycUploadRequest($request);

        if (!$validation['passes']) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please check the required format and upload details.',
                'errors' => $validation['errors'],
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
        $createdDocuments = collect($uploadedDocs)->map(function ($docData) use ($user, $profile) {
            return KycDocument::create([
                'user_id' => $user->id,
                'customer_profile_id' => $profile->id,
                'document_type' => $docData['document_type'],
                'file_name' => $docData['file_name'],
                'mime_type' => $docData['mime_type'],
                'file_size' => $docData['file_size'],
                'storage_path' => $docData['storage_path'],
            ]);
        });

        $profile->update(['kyc_status' => 'pending']);

        return response()->json([
            'success' => true,
            'message' => 'KYC documents uploaded successfully',
            'data' => ['documents' => $createdDocuments->map(fn (KycDocument $document) => $this->formatDocumentResponse($document))],
        ], 201);
    }

    public function storeDocument(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->getOrCreateProfile($user);
        Gate::authorize('update', $profile);

        $validator = validator($request->all(), [
            'document_type' => 'required|string',
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], [
            'document_type.required' => 'The document_type field is required.',
            'document_type.string' => 'The document_type field must be a string.',
            'document.required' => 'The document field is required.',
            'document.file' => 'The document must be a valid uploaded file.',
            'document.mimes' => 'The document must be a file of type: jpg, jpeg, png, pdf.',
            'document.max' => 'The document must not be larger than 5MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if ($this->isLoanSpecificDocumentType($validated['document_type'])) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'document_type' => ['This document type is loan-application-specific and must be uploaded through the loan application document endpoint.'],
                ],
            ], 422);
        }

        $documentType = $this->normalizeDocumentType($validated['document_type']);

        if (!in_array($documentType, KycDocument::types(), true)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'document_type' => ['Invalid document type.'],
                ],
            ], 422);
        }

        if ($this->hasActiveKycDocument($profile, $documentType)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'document_type' => ["The document type '{$documentType}' has already been uploaded and is pending verification."],
                ],
            ], 422);
        }

        $this->deleteRejectedKycDocuments($profile, $documentType);

        $file = $validated['document'];
        $storedName = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());
        $storagePath = $file->storeAs('kyc/' . $profile->user_id, $storedName, 'public');

        $document = KycDocument::create([
            'user_id' => $user->id,
            'customer_profile_id' => $profile->id,
            'document_type' => $documentType,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'storage_path' => $storagePath,
        ]);

        $profile->update(['kyc_status' => 'pending']);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'data' => [
                'document_id' => $document->id,
                'document_type' => $document->document_type,
                'verification_status' => $document->verification_status,
            ],
        ], 201);
    }

    public function listDocuments(Request $request): JsonResponse
    {
        $profile = $request->user()->customerProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Customer profile not found.',
            ], 404);
        }

        $documents = $profile->kycDocuments()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'kyc_status' => $profile->kyc_status,
                'kyc_tier' => $profile->kyc_tier,
                'documents' => $documents->map(fn (KycDocument $document) => $this->formatDocumentResponse($document)),
                'document_statuses' => $this->buildDocumentVerificationSummary($documents->toArray()),
            ],
        ]);
    }

    public function deleteDocument(Request $request, KycDocument $document): JsonResponse
    {
        $user = $request->user();

        if ($document->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized document deletion.',
            ], 403);
        }

        Storage::disk('public')->delete($document->storage_path);
        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully.',
        ]);
    }

    /**
     * Prepare KYC uploads and validate missing files/duplicate types
     */
    private function prepareKycUploads(Request $request, CustomerProfile $profile): array
    {
        $documents = $request->input('documents', []);
        $existingTypes = array_merge(
            $profile->kycDocuments()->where('verification_status', '<>', KycDocument::VERIFICATION_REJECTED)->pluck('document_type')->map(fn ($type) => strtolower($type))->all(),
            collect($profile->kyc_documents ?? [])->pluck('type')->filter()->map(fn ($type) => strtolower($type))->all(),
        );
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
            $this->deleteRejectedKycDocuments($profile, $type);
            $path = $file->store('kyc/' . $profile->user_id, 'public');

            $uploads[] = [
                'document_type' => $type,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'storage_path' => $path,
                'url' => Storage::url($path),
                'status' => 'pending',
                'verified_at' => null,
                'verified_by' => null,
                'uploaded_at' => now()->toISOString(),
            ];
        }

        return ['uploads' => $uploads, 'errors' => $errors];
    }

    private function validateKycUploadRequest(Request $request): array
    {
        $supportedTypes = ['passport_photo', 'passport', 'passport_document', 'selfie', 'id_card', 'id_card_front', 'id_card_back', 'appointment_letter', 'employment_details', 'employer_id_card', 'employment_letter', 'employment_document', 'employment_documents', 'payslip_document', 'govt_id_card', 'government_id', 'government_id_card', 'driver_license', 'utility_bill', 'proof_of_address', 'address_proof', 'nin', 'bvn'];
        $supportedFormats = ['jpg', 'jpeg', 'png', 'pdf'];
        $maxBytes = 5 * 1024 * 1024;

        $validator = validator($request->all(), [
            'documents' => 'required|array|min:1',
            'documents.*.type' => 'required|string|in:' . implode(',', $supportedTypes),
            'documents.*.file' => 'required|file',
        ], [
            'documents.required' => 'The documents field is required and must be sent as multipart/form-data.',
            'documents.array' => 'The documents field must be an array of document entries.',
            'documents.min' => 'At least one document upload entry is required.',
            'documents.*.type.required' => 'Each document entry must include a type value.',
            'documents.*.type.in' => "The document type ':input' is not supported. Accepted type values are: passport_photo, passport, passport_document, selfie, id_card, id_card_front, id_card_back, appointment_letter, employment_details, employer_id_card, employment_letter, employment_document, employment_documents, payslip_document, driver_license, utility_bill, proof_of_address, address_proof, nin, bvn.",
            'documents.*.file.required' => 'Each document entry must include a file upload.',
            'documents.*.file.file' => 'The uploaded file for each document entry must be a valid file.',
        ]);

        $errors = $validator->errors()->toArray();
        $documents = $request->input('documents', []);

        foreach ($documents as $index => $docData) {
            $file = $request->file("documents.{$index}.file");
            $originalName = $file?->getClientOriginalName() ?? 'unknown file';

            if (! $file) {
                continue;
            }

            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($extension === '') {
                $errors["documents.{$index}.file"][] = "The file '{$originalName}' does not include an extension. Use jpg, jpeg, png, or pdf.";
            } elseif (! in_array($extension, $supportedFormats, true)) {
                $errors["documents.{$index}.file"][] = "The file '{$originalName}' uses an unsupported format '{$extension}'. Supported formats are: jpg, jpeg, png, pdf.";
            }

            if ($file->getSize() > $maxBytes) {
                $sizeMb = number_format($file->getSize() / 1024 / 1024, 2);
                $errors["documents.{$index}.file"][] = "The file '{$originalName}' is too large ({$sizeMb}MB). Maximum allowed size is 5MB.";
            }

            if (isset($docData['type']) && ! in_array($docData['type'], $supportedTypes, true)) {
                $errors["documents.{$index}.type"][] = "The type '{$docData['type']}' is invalid. Acceptable values are: passport_photo, passport, passport_document, selfie, id_card, id_card_front, id_card_back, appointment_letter, employment_details, employer_id_card, employment_letter, employment_document, employment_documents, payslip_document, driver_license, utility_bill, proof_of_address, address_proof, nin, bvn.";
            }
        }

        return ['passes' => empty($errors), 'errors' => $errors];
    }

    public function myKyc(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->customerProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Customer profile not found'], 404);
        }

        $documents = $profile->kycDocuments()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'phone']),
                'profile' => [
                    'id' => $profile->id,
                    'kyc_tier' => $profile->kyc_tier,
                    'kyc_status' => $profile->kyc_status,
                    'kyc_verified_at' => $profile->kyc_verified_at,
                    'rejection_reason' => $profile->rejection_reason ?? null,
                ],
                'documents' => $documents->map(fn (KycDocument $document) => $this->formatDocumentResponse($document)),
                'document_statuses' => $this->buildDocumentVerificationSummary($documents),
            ],
        ]);
    }

    public function myEmploymentProfile(Request $request): JsonResponse
    {
        $profile = $request->user()->customerProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Customer profile not found'], 404);
        }

        $employmentProfile = $profile->employmentProfile;
        $employmentDocuments = $profile->kycDocuments()
            ->whereIn('document_type', [
                KycDocument::TYPE_EMPLOYER_ID_CARD,
                KycDocument::TYPE_EMPLOYMENT_LETTER,
                KycDocument::TYPE_EMPLOYMENT_DOCUMENT,
                KycDocument::TYPE_APPOINTMENT_LETTER,
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'employment_profile' => [
                    'employer_type' => $employmentProfile?->employer_type ?? null,
                    'employment_status' => $employmentProfile?->employment_status ?? null,
                    'employment_type' => $employmentProfile?->employment_type ?? null,
                    'retirement_status' => $employmentProfile?->retirement_status ?? null,
                    'employment_year' => $employmentProfile?->employment_year ?? null,
                    'retirement_year' => $employmentProfile?->retirement_year ?? null,
                    'employer_id_number' => $employmentProfile?->employer_id_number ?? null,
                    'payroll_gross' => $employmentProfile?->payroll_gross ?? null,
                    'payroll_net' => $employmentProfile?->payroll_net ?? null,
                    'employment_documents' => $employmentProfile?->employment_documents ?? null,
                    'employment_profile_status' => $employmentProfile?->employment_profile_status ?? 'pending',
                    'employment_profile_reviewed_by' => $employmentProfile?->employment_profile_reviewed_by ?? null,
                    'employment_profile_reviewed_at' => $employmentProfile?->employment_profile_reviewed_at?->toISOString(),
                    'documents' => $employmentDocuments->map(fn (KycDocument $document) => $this->formatDocumentResponse($document)),
                ],
            ],
        ]);
    }

    public function storeEmploymentProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->getOrCreateProfile($user);
        // Do not allow creating a second employment profile for the same customer
        $employmentProfile = $this->getEmploymentProfile($profile);
        if ($employmentProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Employment profile already exists. Use PATCH to update the existing profile.',
            ], 409);
        }

        $validated = $request->validate([
            'employer_type' => 'required|string|in:government,private',
            'employer_id_number' => 'nullable|string|max:255',
            'employment_status' => 'nullable|string|max:100',
            'employment_type' => 'nullable|string|max:100',
            'retirement_status' => 'nullable|string|in:active,retired',
            'employment_year' => 'nullable|integer|min:1900|max:2100',
            'retirement_year' => 'nullable|integer|min:1900|max:2100',
            'payroll_gross' => 'nullable|numeric|min:0',
            'payroll_net' => 'nullable|numeric|min:0',
            'employment_documents' => 'nullable|array',
        ]);

        $employmentProfile = $profile->employmentProfile()->create(array_merge($validated, [
            'employment_profile_status' => 'pending',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Employment profile saved successfully.',
            'data' => $employmentProfile->fresh(),
        ], 201);
    }

    public function updateEmploymentProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->customerProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Customer profile not found'], 404);
        }

        $employmentProfile = $this->getEmploymentProfile($profile);
        if (! $employmentProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Employment profile not found. Create it via POST /user/kyc/employment-profiles.',
            ], 404);
        }

        if (in_array($employmentProfile->employment_profile_status, ['verified', 'under_review'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Employment profile cannot be changed during review or after verification.',
                'errors' => ['employment_profile_status' => ['Employment profiles under review or already verified cannot be updated by the customer.']],
            ], 422);
        }

        $validated = $request->validate([
            'employer_type' => 'required|string|in:government,private',
            'employer_id_number' => 'nullable|string|max:255',
            'employment_status' => 'nullable|string|max:100',
            'employment_type' => 'nullable|string|max:100',
            'retirement_status' => 'nullable|string|in:active,retired',
            'employment_year' => 'nullable|integer|min:1900|max:2100',
            'retirement_year' => 'nullable|integer|min:1900|max:2100',
            'payroll_gross' => 'nullable|numeric|min:0',
            'payroll_net' => 'nullable|numeric|min:0',
            'employment_documents' => 'nullable|array',
        ]);

        $employmentProfile->update(array_merge($validated, [
            'employment_profile_status' => 'pending',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Employment profile updated successfully.',
            'data' => $employmentProfile->fresh(),
        ]);
    }

    public function storeEmploymentDocuments(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->getOrCreateProfile($user);
        $employmentProfile = $this->getOrCreateEmploymentProfile($profile);

        if (in_array($employmentProfile->employment_profile_status, ['verified', 'under_review'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Employment documents cannot be changed during review or after verification.',
                'errors' => ['employment_profile_status' => ['Employment documents cannot be updated while the profile is under review or already verified.']],
            ], 422);
        }

        $validation = $this->validateKycUploadRequest($request);

        if (! $validation['passes']) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please check the upload data and file details.',
                'errors' => $validation['errors'],
            ], 422);
        }

        $result = $this->prepareEmploymentUploads($request, $profile);
        if (! empty($result['errors'])) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please fix the upload data.',
                'errors' => $result['errors'],
            ], 422);
        }

        $createdDocuments = collect($result['uploads'])->map(function ($docData) use ($user, $profile) {
            return KycDocument::create([
                'user_id' => $user->id,
                'customer_profile_id' => $profile->id,
                'document_type' => $docData['document_type'],
                'file_name' => $docData['file_name'],
                'mime_type' => $docData['mime_type'],
                'file_size' => $docData['file_size'],
                'storage_path' => $docData['storage_path'],
            ]);
        });

        $employmentDocuments = $profile->kycDocuments()
            ->whereIn('document_type', $this->getEmploymentDocumentTypes())
            ->orderBy('created_at', 'desc')
            ->get();

        $employmentProfile->update([
            'employment_profile_status' => $employmentProfile->employment_profile_status === 'under_review' ? 'under_review' : 'pending',
            'employment_documents' => $this->summarizeEmploymentDocuments($employmentDocuments),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Employment documents uploaded successfully.',
            'data' => ['documents' => $createdDocuments->map(fn (KycDocument $document) => $this->formatDocumentResponse($document))],
        ], 201);
    }

    public function showUserEmploymentProfile(User $user): JsonResponse
    {
        $profile = $user->customerProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Customer profile not found'], 404);
        }

        $employmentProfile = $this->getEmploymentProfile($profile);
        $employmentDocuments = $profile->kycDocuments()
            ->whereIn('document_type', [
                KycDocument::TYPE_EMPLOYER_ID_CARD,
                KycDocument::TYPE_EMPLOYMENT_LETTER,
                KycDocument::TYPE_EMPLOYMENT_DOCUMENT,
                KycDocument::TYPE_APPOINTMENT_LETTER,
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'employment_profile' => [
                    'employer_type' => $employmentProfile?->employer_type ?? null,
                    'employment_status' => $employmentProfile?->employment_status ?? null,
                    'employment_type' => $employmentProfile?->employment_type ?? null,
                    'retirement_status' => $employmentProfile?->retirement_status ?? null,
                    'employment_year' => $employmentProfile?->employment_year ?? null,
                    'retirement_year' => $employmentProfile?->retirement_year ?? null,
                    'employer_id_number' => $employmentProfile?->employer_id_number ?? null,
                    'payroll_gross' => $employmentProfile?->payroll_gross ?? null,
                    'payroll_net' => $employmentProfile?->payroll_net ?? null,
                    'employment_documents' => $employmentProfile?->employment_documents ?? null,
                    'employment_profile_status' => $employmentProfile?->employment_profile_status ?? 'pending',
                    'employment_profile_reviewed_by' => $employmentProfile?->employment_profile_reviewed_by ?? null,
                    'employment_profile_reviewed_at' => $employmentProfile?->employment_profile_reviewed_at?->toISOString(),
                    'documents' => $employmentDocuments->map(fn (KycDocument $document) => $this->formatDocumentResponse($document)),
                ],
            ],
        ]);
    }

    public function storeUserEmploymentProfile(Request $request, User $user): JsonResponse
    {
        $profile = $this->getOrCreateProfile($user);
        $employmentProfile = $this->getEmploymentProfile($profile);
        if ($employmentProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Employment profile already exists for this user. Use PATCH to update it.',
            ], 409);
        }

        $validated = $request->validate([
            'employer_type' => 'required|string|in:government,private',
            'employer_id_number' => 'nullable|string|max:255',
            'employment_status' => 'nullable|string|max:100',
            'employment_type' => 'nullable|string|max:100',
            'retirement_status' => 'nullable|string|in:active,retired',
            'employment_year' => 'nullable|integer|min:1900|max:2100',
            'retirement_year' => 'nullable|integer|min:1900|max:2100',
            'payroll_gross' => 'nullable|numeric|min:0',
            'payroll_net' => 'nullable|numeric|min:0',
            'employment_documents' => 'nullable|array',
        ]);

        $employmentProfile = $profile->employmentProfile()->create(array_merge($validated, [
            'employment_profile_status' => 'pending',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Employment profile saved successfully.',
            'data' => $employmentProfile->fresh(),
        ], 201);
    }

    public function updateUserEmploymentProfile(Request $request, User $user): JsonResponse
    {
        $profile = $user->customerProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Customer profile not found'], 404);
        }

        $employmentProfile = $this->getEmploymentProfile($profile);
        if (! $employmentProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Employment profile not found. Create it via POST /users/{user}/kyc/employment-profile.',
            ], 404);
        }

        if ($employmentProfile->employment_profile_status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Employment profile cannot be changed after verification.',
                'errors' => ['employment_profile_status' => ['Verified employment profiles cannot be updated.']],
            ], 422);
        }

        if ($employmentProfile->employment_profile_status !== 'under_review') {
            return response()->json([
                'success' => false,
                'message' => 'Admin and staff can only update employment profiles when they are under review.',
                'errors' => ['employment_profile_status' => ['This profile must be under review before admins or staff can update it.']],
            ], 422);
        }

        $validated = $request->validate([
            'employer_type' => 'required|string|in:government,private',
            'employer_id_number' => 'nullable|string|max:255',
            'employment_status' => 'nullable|string|max:100',
            'employment_type' => 'nullable|string|max:100',
            'retirement_status' => 'nullable|string|in:active,retired',
            'employment_year' => 'nullable|integer|min:1900|max:2100',
            'retirement_year' => 'nullable|integer|min:1900|max:2100',
            'payroll_gross' => 'nullable|numeric|min:0',
            'payroll_net' => 'nullable|numeric|min:0',
            'employment_documents' => 'nullable|array',
        ]);

        $employmentProfile->update(array_merge($validated, [
            'employment_profile_status' => $employmentProfile->employment_profile_status === 'under_review' ? 'under_review' : 'pending',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Employment profile updated successfully.',
            'data' => $employmentProfile->fresh(),
        ]);
    }

    public function storeUserEmploymentDocuments(Request $request, User $user): JsonResponse
    {
        $profile = $this->getOrCreateProfile($user);
        $employmentProfile = $this->getOrCreateEmploymentProfile($profile);

        if ($employmentProfile->employment_profile_status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Employment documents cannot be changed after verification.',
                'errors' => ['employment_profile_status' => ['Verified employment profiles cannot be updated.']],
            ], 422);
        }

        if (! $employmentProfile->wasRecentlyCreated && $employmentProfile->employment_profile_status !== 'under_review') {
            return response()->json([
                'success' => false,
                'message' => 'Employment documents can only be updated by admin or staff when the profile is under review.',
                'errors' => ['employment_profile_status' => ['This profile must be under review before employment documents can be updated by staff.']],
            ], 422);
        }

        $validation = $this->validateKycUploadRequest($request);

        if (! $validation['passes']) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please check the upload data and file details.',
                'errors' => $validation['errors'],
            ], 422);
        }

        $result = $this->prepareEmploymentUploads($request, $profile);
        if (! empty($result['errors'])) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please fix the upload data.',
                'errors' => $result['errors'],
            ], 422);
        }

        $createdDocuments = collect($result['uploads'])->map(function ($docData) use ($user, $profile) {
            return KycDocument::create([
                'user_id' => $user->id,
                'customer_profile_id' => $profile->id,
                'document_type' => $docData['document_type'],
                'file_name' => $docData['file_name'],
                'mime_type' => $docData['mime_type'],
                'file_size' => $docData['file_size'],
                'storage_path' => $docData['storage_path'],
            ]);
        });

        $employmentDocuments = $profile->kycDocuments()
            ->whereIn('document_type', $this->getEmploymentDocumentTypes())
            ->orderBy('created_at', 'desc')
            ->get();

        $employmentProfile->update([
            'employment_profile_status' => $employmentProfile->employment_profile_status === 'under_review' ? 'under_review' : 'pending',
            'employment_documents' => $this->summarizeEmploymentDocuments($employmentDocuments),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Employment documents uploaded successfully.',
            'data' => ['documents' => $createdDocuments->map(fn (KycDocument $document) => $this->formatDocumentResponse($document))],
        ], 201);
    }

    private function prepareEmploymentUploads(Request $request, CustomerProfile $profile): array
    {
        $documents = $request->input('documents', []);
        $existingTypes = $profile->kycDocuments()
            ->where('verification_status', '<>', KycDocument::VERIFICATION_REJECTED)
            ->pluck('document_type')
            ->map(fn ($type) => strtolower($type))
            ->all();

        $allowedTypes = [
            KycDocument::TYPE_EMPLOYER_ID_CARD,
            KycDocument::TYPE_EMPLOYMENT_LETTER,
            KycDocument::TYPE_EMPLOYMENT_DOCUMENT,
            KycDocument::TYPE_APPOINTMENT_LETTER,
            KycDocument::TYPE_PAYSLIP_DOCUMENT,
        ];

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

            if (! in_array($type, $allowedTypes, true)) {
                $errors["documents.{$index}.type"][] = "The document type '{$type}' is not supported for employment profile uploads.";
                continue;
            }

            if (! $file) {
                $errors["documents.{$index}.file"][] = 'The file is required for this document entry.';
                continue;
            }

            if (in_array(strtolower($type), $existingTypes, true)) {
                $errors["documents.{$index}.type"][] = "The document type '{$type}' has already been uploaded.";
                continue;
            }

            if (in_array($type, $seenTypes, true)) {
                $errors["documents.{$index}.type"][] = "The document type '{$type}' is duplicated in the request.";
                continue;
            }

            $seenTypes[] = $type;
            $this->deleteRejectedKycDocuments($profile, $type);
            $path = $file->store('kyc/' . $profile->user_id, 'public');

            $uploads[] = [
                'document_type' => $type,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'storage_path' => $path,
                'url' => Storage::url($path),
                'status' => 'pending',
                'verified_at' => null,
            ];
        }

        return ['uploads' => $uploads, 'errors' => $errors];
    }

    public function verifyEmploymentProfile(Request $request, CustomerProfile $profile): JsonResponse
    {
        Gate::authorize('verifyKyc', $profile);

        $employmentProfile = $profile->employmentProfile;
        if (! $employmentProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Employment profile not found for this customer.',
            ], 404);
        }

        if ($employmentProfile->employment_profile_status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Employment profile is already verified.',
            ], 422);
        }

        if (empty($employmentProfile->employer_type)) {
            return response()->json([
                'success' => false,
                'message' => 'Employer type is required before employment profile verification.',
            ], 422);
        }

        $employmentDocuments = $profile->kycDocuments()
            ->whereIn('document_type', $this->getEmploymentDocumentTypes())
            ->orderBy('created_at', 'desc')
            ->get();

        if ($employmentDocuments->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'At least one employment document must be uploaded before employment profile verification.',
                'errors' => [
                    'employment_documents' => ['Upload at least one employment document before verifying the profile.'],
                ],
                'data' => [
                    'employment_profile_status' => $employmentProfile->employment_profile_status,
                    'employment_documents' => [],
                ],
            ], 422);
        }

        $pendingOrRejectedDocuments = $employmentDocuments->filter(fn (KycDocument $document) => $document->verification_status !== KycDocument::VERIFICATION_APPROVED);
        if ($pendingOrRejectedDocuments->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'All uploaded employment documents must be approved before verifying the employment profile.',
                'errors' => [
                    'employment_documents' => ['Employment profile verification requires all uploaded employment documents to have approved status.'],
                ],
                'data' => [
                    'employment_profile_status' => $employmentProfile->employment_profile_status,
                    'employment_documents' => $this->summarizeEmploymentDocuments($employmentDocuments),
                ],
            ], 422);
        }

        $employmentProfile->update([
            'employment_profile_status' => 'verified',
            'employment_profile_reviewed_by' => auth()->id(),
            'employment_profile_reviewed_at' => now(),
            'employment_documents' => $this->summarizeEmploymentDocuments($employmentDocuments),
        ]);

        $profile->update(['kyc_tier' => max(2, $profile->kyc_tier ?? 1)]);

        return response()->json([
            'success' => true,
            'message' => 'Employment profile verified successfully.',
            'data' => $employmentProfile->fresh(),
        ]);
    }

    public function rejectEmploymentProfile(Request $request, CustomerProfile $profile): JsonResponse
    {
        Gate::authorize('rejectKyc', $profile);

        $employmentProfile = $profile->employmentProfile;
        if (! $employmentProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Employment profile not found for this customer.',
            ], 404);
        }

        $validator = validator($request->all(), [
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $employmentProfile->update([
            'employment_profile_status' => 'rejected',
            'employment_profile_reviewed_by' => auth()->id(),
            'employment_profile_reviewed_at' => now(),
            'employment_documents' => $employmentProfile->employment_documents,
        ]);

        $profile->update(['kyc_tier' => $this->calculateKycTier($profile->kycDocuments()->get(), $profile)]);

        return response()->json([
            'success' => true,
            'message' => 'Employment profile rejected.',
            'data' => $employmentProfile->fresh(),
        ]);
    }

    public function markEmploymentProfileUnderReview(Request $request, CustomerProfile $profile): JsonResponse
    {
        Gate::authorize('verifyKyc', $profile);

        $employmentProfile = $profile->employmentProfile;
        if (! $employmentProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Employment profile not found for this customer.',
            ], 404);
        }

        if ($employmentProfile->employment_profile_status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Verified employment profiles cannot be moved back to under review.',
            ], 422);
        }

        $employmentProfile->update([
            'employment_profile_status' => 'under_review',
            'employment_profile_reviewed_by' => auth()->id(),
            'employment_profile_reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Employment profile marked as under review.',
            'data' => $employmentProfile->fresh(),
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

        $validation = $this->validateKycUploadRequest($request);

        if (!$validation['passes']) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please check the upload data and file details.',
                'errors' => $validation['errors'],
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

        $createdDocuments = collect($uploadedDocs)->map(function ($docData) use ($user, $profile) {
            return KycDocument::create([
                'user_id' => $user->id,
                'customer_profile_id' => $profile->id,
                'document_type' => $docData['document_type'],
                'file_name' => $docData['file_name'],
                'mime_type' => $docData['mime_type'],
                'file_size' => $docData['file_size'],
                'storage_path' => $docData['storage_path'],
            ]);
        });

        $profile->update(['kyc_status' => 'pending']);

        return response()->json([
            'success' => true,
            'message' => 'KYC documents uploaded successfully',
            'data' => ['documents' => $createdDocuments->map(fn (KycDocument $document) => $this->formatDocumentResponse($document))],
        ], 201);
    }

    public function uploadPassportDocument(Request $request): JsonResponse
    {
        return $this->uploadSingleDocument($request, 'passport_document');
    }

    public function uploadIdCard(Request $request): JsonResponse
    {
        return $this->uploadSingleDocument($request, 'id_card');
    }

    public function uploadPassportPhoto(Request $request): JsonResponse
    {
        return $this->uploadSingleDocument($request, 'passport_photo');
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
        $profile = $this->getOrCreateProfile($user);
        Gate::authorize('update', $profile);

        $validator = validator($request->all(), [
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], [
            'file.required' => 'The document field is required.',
            'file.file' => 'The document must be a valid file upload.',
            'file.mimes' => 'The document must be a file of type: jpg, jpeg, png, pdf.',
            'file.max' => 'The document must not be larger than 5MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $documentType = $this->normalizeDocumentType($type);

        $file = $request->file('file');

        if ($this->hasActiveKycDocument($profile, $documentType)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'file' => ["The document type '{$documentType}' has already been uploaded and is pending verification."],
                ],
            ], 422);
        }

        $this->deleteRejectedKycDocuments($profile, $documentType);

        $storedName = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());
        $storagePath = $file->storeAs('kyc/' . $profile->user_id, $storedName, 'public');

        $document = KycDocument::create([
            'user_id' => $user->id,
            'customer_profile_id' => $profile->id,
            'document_type' => $documentType,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'storage_path' => $storagePath,
        ]);

        $profile->update(['kyc_status' => 'pending']);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'data' => $this->formatDocumentResponse($document),
        ], 201);
    }

    private function normalizeDocumentType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'passport_photo' => KycDocument::TYPE_PASSPORT_PHOTO,
            'passport_document', 'passport' => KycDocument::TYPE_PASSPORT_DOCUMENT,
            'selfie' => KycDocument::TYPE_SELFIE,
            'id_card' => KycDocument::TYPE_ID_CARD_FRONT,
            'id_card_front' => KycDocument::TYPE_ID_CARD_FRONT,
            'id_card_back' => KycDocument::TYPE_ID_CARD_BACK,
            'driver_license', 'drivers_license' => KycDocument::TYPE_DRIVERS_LICENSE,
            'utility_bill' => KycDocument::TYPE_UTILITY_BILL,
            'proof_of_address', 'address_proof' => KycDocument::TYPE_PROOF_OF_ADDRESS,
            'appointment_letter', 'employment_details' => KycDocument::TYPE_APPOINTMENT_LETTER,
            'employer_id_card' => KycDocument::TYPE_EMPLOYER_ID_CARD,
            'employment_letter' => KycDocument::TYPE_EMPLOYMENT_LETTER,
            'employment_document', 'employment_documents' => KycDocument::TYPE_EMPLOYMENT_DOCUMENT,
            'payslip', 'payslip_document', 'salary_slip', 'salary_document' => KycDocument::TYPE_PAYSLIP_DOCUMENT,
            'govt_id_card', 'government_id', 'government_id_card' => KycDocument::TYPE_ID_CARD_FRONT,
            'nin' => KycDocument::TYPE_NIN,
            'bvn' => KycDocument::TYPE_BVN,
            default => strtoupper(str_replace(' ', '_', $type)),
        };
    }

    private function formatDocumentResponse(KycDocument $document): array
    {
        return [
            'document_id' => $document->id,
            'document_type' => $document->document_type,
            'file_name' => $document->file_name,
            'verification_status' => $document->verification_status,
            'rejection_reason' => $document->rejection_reason,
            'reviewed_by' => $document->reviewed_by,
            'reviewed_at' => $document->reviewed_at?->toISOString(),
            'uploaded_at' => $document->created_at?->toISOString(),
        ];
    }

    private static function loanSpecificDocumentTypes(): array
    {
        return [
            'bank_statement',
            'proof_income',
            'guarantor_form',
        ];
    }

    private function isLoanSpecificDocumentType(string $type): bool
    {
        return in_array(strtolower(trim($type)), self::loanSpecificDocumentTypes(), true);
    }

    private function getOrCreateProfile(User $user): CustomerProfile
    {
        $profile = $user->customerProfile;

        if (!$profile) {
            $profile = CustomerProfile::create([
                'user_id' => $user->id,
                'kyc_status' => 'pending',
            ]);
        }

        return $profile;
    }

    private function getEmploymentProfile(CustomerProfile $profile): ?CustomerEmploymentProfile
    {
        return $profile->employmentProfile;
    }

    private function getOrCreateEmploymentProfile(CustomerProfile $profile): CustomerEmploymentProfile
    {
        return $profile->employmentProfile()->firstOrCreate(
            ['customer_profile_id' => $profile->id],
            ['employment_profile_status' => 'pending']
        );
    }

    private function hasActiveKycDocument(CustomerProfile $profile, string $documentType): bool
    {
        return $profile->kycDocuments()
            ->where('document_type', $documentType)
            ->where('verification_status', '<>', KycDocument::VERIFICATION_REJECTED)
            ->exists();
    }

    private function deleteRejectedKycDocuments(CustomerProfile $profile, string $documentType): void
    {
        $rejectedDocs = $profile->kycDocuments()
            ->where('document_type', $documentType)
            ->where('verification_status', KycDocument::VERIFICATION_REJECTED)
            ->get();

        foreach ($rejectedDocs as $doc) {
            Storage::disk('public')->delete($doc->storage_path);
            $doc->delete();
        }
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
        $documents = $profile->kycDocuments()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $profile->user->only(['id', 'first_name', 'last_name', 'email', 'phone']),
                'profile' => [
                    'id' => $profile->id,
                    'kyc_tier' => $profile->kyc_tier,
                    'kyc_status' => $profile->kyc_status,
                    'kyc_verified_at' => $profile->kyc_verified_at,
                    'rejection_reason' => $profile->rejection_reason ?? null,
                ],
                'documents' => $documents->map(fn (KycDocument $document) => $this->formatDocumentResponse($document)),
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
        $documents = $profile->kycDocuments()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'phone']),
                'profile' => [
                    'id' => $profile->id,
                    'kyc_status' => $profile->kyc_status,
                    'kyc_verified_at' => $profile->kyc_verified_at,
                    'kyc_tier' => $profile->kyc_tier,
                    'rejection_reason' => $profile->rejection_reason ?? null,
                ],
                'employment_profile' => [
                    'employer_type' => $profile->employmentProfile?->employer_type ?? null,
                    'employment_status' => $profile->employmentProfile?->employment_status ?? null,
                    'employer_id_number' => $profile->employmentProfile?->employer_id_number ?? null,
                    'payroll_gross' => $profile->employmentProfile?->payroll_gross ?? null,
                    'payroll_net' => $profile->employmentProfile?->payroll_net ?? null,
                    'employment_documents' => $profile->employmentProfile?->employment_documents ?? null,
                    'employment_profile_status' => $profile->employmentProfile?->employment_profile_status ?? 'pending',
                    'employment_profile_reviewed_by' => $profile->employmentProfile?->employment_profile_reviewed_by ?? null,
                    'employment_profile_reviewed_at' => $profile->employmentProfile?->employment_profile_reviewed_at?->toISOString(),
                ],
                'documents' => $documents->map(fn (KycDocument $document) => $this->formatDocumentResponse($document)),
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
            'verified_types.*' => 'required|string',
            'tier' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $inputTypes = collect($validator->validated()['verified_types'])->map(fn ($type) => $this->normalizeDocumentType($type))->unique();
        $documents = $profile->kycDocuments()->get();

        if ($documents->isNotEmpty()) {
            $allowedTypes = KycDocument::types();
            $invalidTypes = $inputTypes->diff($allowedTypes);

            if ($invalidTypes->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => [
                        'verified_types' => ['One or more document types are invalid.'],
                    ],
                ], 422);
            }

            $found = false;
            foreach ($documents as $document) {
                if (in_array($document->document_type, $inputTypes->all(), true)) {
                    $document->verification_status = KycDocument::VERIFICATION_APPROVED;
                    $document->reviewed_by = auth()->id();
                    $document->reviewed_at = now();
                    $document->save();
                    $found = true;
                }
            }

            if (!$found) {
                return response()->json([
                    'success' => false,
                    'message' => 'No matching documents were found for verification.',
                ], 404);
            }

            $profile->refresh();
            $currentStatus = $this->determineKycStatus($profile->kycDocuments);
            $approvedTypes = $this->getApprovedDocumentTypes($profile->kycDocuments);

            if ($request->input('tier') === 2 && ! $this->canAssignTier2($approvedTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot assign KYC tier 2 without approved documentation or a verified employment profile.',
                    'errors' => [
                        'tier' => ['Tier 2 requires either an approved government-issued ID combination or a verified employment profile.'],
                    ],
                ], 422);
            }

            $profile->update([
                'kyc_reviewed_by' => auth()->id(),
                'verification_notes' => $request->notes,
                'kyc_tier' => $request->input('tier') ?? $this->calculateKycTier($profile->kycDocuments, $profile),
                'kyc_status' => $currentStatus,
                'kyc_verified_at' => $currentStatus === 'verified' ? now() : $profile->kyc_verified_at,
            ]);

            $profile->refresh();
            $documents = $profile->kycDocuments()->get();

            Log::info('KYC verified by user: ' . auth()->id() . ' for profile: ' . $profile->id);

            return response()->json([
                'success' => true,
                'message' => 'KYC verified successfully',
                'data' => [
                    'profile' => [
                        'id' => $profile->id,
                        'kyc_status' => $profile->kyc_status,
                        'kyc_verified_at' => $profile->kyc_verified_at,
                        'kyc_tier' => $profile->kyc_tier,
                    ],
                    'documents' => $documents->map(fn (KycDocument $document) => $this->formatDocumentResponse($document)),
                    'document_statuses' => $this->buildDocumentVerificationSummary($documents),
                ],
            ]);
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
            'kyc_tier' => $request->input('tier') ?? $this->calculateKycTier($documents),
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
                'kyc_tier' => $profile->kyc_tier,
                'kyc_documents' => $documents,
                'document_statuses' => $this->buildDocumentVerificationSummary($documents),
            ],
        ]);
    }

    private function determineKycStatus(iterable $documents): string
    {
        $docs = collect($documents)->map(function ($doc) {
            return [
                'type' => strtoupper($doc['type'] ?? $doc->document_type ?? ''),
                'status' => strtoupper($doc['status'] ?? $doc->verification_status ?? 'PENDING'),
            ];
        });

        $approvedTypes = $docs->filter(fn ($doc) => $doc['status'] === KycDocument::VERIFICATION_APPROVED)
            ->pluck('type')
            ->unique()
            ->all();

        $hasIdCard = in_array(KycDocument::TYPE_ID_CARD_FRONT, $approvedTypes, true)
            && in_array(KycDocument::TYPE_ID_CARD_BACK, $approvedTypes, true);

        $hasPassport = in_array(KycDocument::TYPE_PASSPORT_PHOTO, $approvedTypes, true)
            && in_array(KycDocument::TYPE_PASSPORT_DOCUMENT, $approvedTypes, true);

        $hasDriverLicense = in_array(KycDocument::TYPE_DRIVERS_LICENSE, $approvedTypes, true);
        $hasUtilityBill = in_array(KycDocument::TYPE_UTILITY_BILL, $approvedTypes, true);
        $hasLegacyPassport = in_array(KycDocument::TYPE_PASSPORT, $approvedTypes, true);

        return ($hasIdCard || $hasPassport || $hasDriverLicense || $hasUtilityBill || $hasLegacyPassport)
            ? 'verified'
            : 'pending';
    }

    private function calculateKycTier(iterable $documents, ?CustomerProfile $profile = null): ?int
    {
        $approvedTypes = collect($documents)
            ->map(function ($doc) {
                return [
                    'type' => strtoupper($doc['type'] ?? $doc->document_type ?? ''),
                    'status' => strtoupper($doc['status'] ?? $doc->verification_status ?? 'PENDING'),
                ];
            })
            ->filter(fn ($doc) => $doc['type'] !== '')
            ->filter(fn ($doc) => $doc['status'] === KycDocument::VERIFICATION_APPROVED)
            ->pluck('type')
            ->unique()
            ->all();

        if ($this->canAssignTier3($approvedTypes)) {
            return 3;
        }

        if ($this->canAssignTier2($approvedTypes, $profile)) {
            return 2;
        }

        if (count($approvedTypes) >= 1) {
            return 1;
        }

        return null;
    }

    private function getApprovedDocumentTypes(iterable $documents): array
    {
        return collect($documents)
            ->map(function ($doc) {
                return [
                    'type' => strtoupper($doc['type'] ?? $doc->document_type ?? ''),
                    'status' => strtoupper($doc['status'] ?? $doc->verification_status ?? 'PENDING'),
                ];
            })
            ->filter(fn ($doc) => $doc['type'] !== '')
            ->filter(fn ($doc) => $doc['status'] === KycDocument::VERIFICATION_APPROVED)
            ->pluck('type')
            ->unique()
            ->all();
    }

    private function canAssignTier2(array $approvedTypes, ?CustomerProfile $profile = null): bool
    {
        if ($profile?->employmentProfile?->employment_profile_status === 'verified') {
            return true;
        }

        return $this->hasApprovedGovernmentId($approvedTypes);
    }

    private function canAssignTier3(array $approvedTypes): bool
    {
        return count($approvedTypes) >= 3 && $this->hasApprovedGovernmentId($approvedTypes);
    }

    private function hasApprovedGovernmentId(array $approvedTypes): bool
    {
        $hasIdCard = in_array(KycDocument::TYPE_ID_CARD_FRONT, $approvedTypes, true)
            && in_array(KycDocument::TYPE_ID_CARD_BACK, $approvedTypes, true);

        $hasPassport = in_array(KycDocument::TYPE_PASSPORT_PHOTO, $approvedTypes, true)
            && in_array(KycDocument::TYPE_PASSPORT_DOCUMENT, $approvedTypes, true);

        $hasLegacyPassport = in_array(KycDocument::TYPE_PASSPORT, $approvedTypes, true);

        return $hasIdCard || $hasPassport || $hasLegacyPassport;
    }

    private function buildDocumentVerificationSummary(iterable $documents): array
    {
        return collect($documents)->mapWithKeys(function ($doc) {
            $type = strtoupper($doc['type'] ?? $doc->document_type ?? 'UNKNOWN');
            $status = strtoupper($doc['status'] ?? $doc->verification_status ?? 'PENDING');
            return [$type => $status];
        })->all();
    }

    private function getEmploymentDocumentTypes(): array
    {
        return [
            KycDocument::TYPE_EMPLOYER_ID_CARD,
            KycDocument::TYPE_EMPLOYMENT_LETTER,
            KycDocument::TYPE_EMPLOYMENT_DOCUMENT,
            KycDocument::TYPE_APPOINTMENT_LETTER,
            KycDocument::TYPE_PAYSLIP_DOCUMENT,
        ];
    }

    private function summarizeEmploymentDocuments(iterable $documents): array
    {
        return collect($documents)->map(function ($document) {
            return [
                'id' => $document->id ?? ($document['id'] ?? null),
                'document_type' => $document->document_type ?? ($document['document_type'] ?? null),
                'file_name' => $document->file_name ?? ($document['file_name'] ?? null),
                'status' => $document->verification_status ?? ($document['status'] ?? null),
                'uploaded_at' => optional($document->created_at)->toISOString() ?? ($document['uploaded_at'] ?? null),
                'verified_at' => optional($document->reviewed_at)->toISOString() ?? ($document['verified_at'] ?? null),
            ];
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
        $allowedTypes = array_merge(KycDocument::types(), ['BANK_STATEMENT', 'PROOF_INCOME']);

        if (!in_array($normalizedType, $allowedTypes, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported document type.',
            ], 422);
        }

        $documents = KycDocument::where('customer_profile_id', $profile->id)
            ->where('document_type', $normalizedType)
            ->get();

        if ($documents->isNotEmpty()) {
            foreach ($documents as $document) {
                $document->verification_status = KycDocument::VERIFICATION_APPROVED;
                $document->reviewed_by = auth()->id();
                $document->reviewed_at = now();
                $document->save();
            }

            if (in_array($normalizedType, $this->getEmploymentDocumentTypes(), true) && $profile->employmentProfile) {
                $profile->employmentProfile->update([
                    'employment_documents' => $this->summarizeEmploymentDocuments($profile->kycDocuments()->whereIn('document_type', $this->getEmploymentDocumentTypes())->get()),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Document verified successfully',
                'data' => [
                    'document_type' => $normalizedType,
                    'profile' => [
                        'id' => $profile->id,
                        'kyc_status' => $profile->kyc_status,
                        'kyc_verified_at' => $profile->kyc_verified_at,
                        'kyc_tier' => $profile->kyc_tier,
                    ],
                    'documents' => $profile->kycDocuments()->get()->map(fn (KycDocument $document) => $this->formatDocumentResponse($document)),
                ],
            ]);
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
        $documents = $profile->kycDocuments()->get();

        if ($documents->isEmpty()) {
            $documents = $profile->kyc_documents ?? [];
        }

        return response()->json([
            'success' => true,
            'message' => 'KYC rejected',
            'data' => [
                'profile' => [
                    'id' => $profile->id,
                    'kyc_tier' => $profile->kyc_tier,
                    'kyc_status' => $profile->kyc_status,
                    'kyc_verified_at' => $profile->kyc_verified_at,
                    'rejection_reason' => $profile->rejection_reason,
                ],
                'documents' => is_iterable($documents) ? collect($documents)->map(fn ($doc) => $doc instanceof KycDocument ? $this->formatDocumentResponse($doc) : $doc) : [],
                'document_statuses' => $this->buildDocumentVerificationSummary($documents),
            ],
        ]);
    }
}


