<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanDocumentType;
use App\Models\LoanProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class LoanDocumentTypeController extends Controller
{
    /**
     * List all loan document types
     * GET /api/v1/admin/loan-document-types
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LoanDocumentType::class);

        $query = LoanDocumentType::query();

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $types = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $types->items(),
            'meta' => [
                'total' => $types->total(),
                'per_page' => $types->perPage(),
                'current_page' => $types->currentPage(),
                'last_page' => $types->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new loan document type
     * POST /api/v1/admin/loan-document-types
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', LoanDocumentType::class);

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:loan_document_types,code|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'required' => 'nullable|boolean',
            'active' => 'nullable|boolean',
        ], [
            'code.unique' => 'A document type with this code already exists.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $documentType = LoanDocumentType::create([
            'code' => strtoupper(str_replace(' ', '_', $request->input('code'))),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'required' => $request->input('required', true),
            'active' => $request->input('active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Loan document type created successfully.',
            'data' => $documentType,
        ], 201);
    }

    /**
     * Get a specific loan document type
     * GET /api/v1/admin/loan-document-types/{documentType}
     */
    public function show(Request $request, LoanDocumentType $documentType): JsonResponse
    {
        Gate::authorize('view', $documentType);

        $data = $documentType->load('loanProducts');

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Update a loan document type
     * PUT /api/v1/admin/loan-document-types/{documentType}
     */
    public function update(Request $request, LoanDocumentType $documentType): JsonResponse
    {
        Gate::authorize('update', $documentType);

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|unique:loan_document_types,code,' . $documentType->id . '|max:50',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'required' => 'nullable|boolean',
            'active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('code')) {
            $documentType->code = strtoupper(str_replace(' ', '_', $request->input('code')));
        }
        if ($request->has('name')) {
            $documentType->name = $request->input('name');
        }
        if ($request->has('description')) {
            $documentType->description = $request->input('description');
        }
        if ($request->has('required')) {
            $documentType->required = $request->input('required');
        }
        if ($request->has('active')) {
            $documentType->active = $request->input('active');
        }

        $documentType->save();

        return response()->json([
            'success' => true,
            'message' => 'Loan document type updated successfully.',
            'data' => $documentType,
        ]);
    }

    /**
     * Delete a loan document type
     * DELETE /api/v1/admin/loan-document-types/{documentType}
     */
    public function destroy(Request $request, LoanDocumentType $documentType): JsonResponse
    {
        Gate::authorize('delete', $documentType);

        $documentType->loanProducts()->detach();
        $documentType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Loan document type deleted successfully.',
        ]);
    }

    /**
     * Attach document types to a loan product
     * POST /api/v1/admin/loan-products/{loanProduct}/document-types
     */
    public function attachToProduct(Request $request, LoanProduct $loanProduct): JsonResponse
    {
        Gate::authorize('update', $loanProduct);

        $validator = Validator::make($request->all(), [
            'document_type_ids' => 'required|array|min:1',
            'document_type_ids.*' => 'uuid|exists:loan_document_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $documentTypeIds = $request->input('document_type_ids');
        
        // Sync to avoid duplicates
        $loanProduct->requiredDocumentTypes()->syncWithoutDetaching($documentTypeIds);

        return response()->json([
            'success' => true,
            'message' => 'Document types attached to loan product successfully.',
            'data' => $loanProduct->load('requiredDocumentTypes'),
        ]);
    }

    /**
     * Remove document types from a loan product
     * DELETE /api/v1/admin/loan-products/{loanProduct}/document-types/{documentType}
     */
    public function detachFromProduct(Request $request, LoanProduct $loanProduct, LoanDocumentType $documentType): JsonResponse
    {
        Gate::authorize('update', $loanProduct);

        $loanProduct->requiredDocumentTypes()->detach($documentType);

        return response()->json([
            'success' => true,
            'message' => 'Document type removed from loan product successfully.',
        ]);
    }
}
