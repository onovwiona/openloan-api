<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class KycController extends Controller
{
    /**
     * Upload KYC document
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_type' => 'required|in:id_card,passport,utility_bill,bank_statement',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $file = $request->file('file');
        $path = $file->store('kyc/' . $user->id, 'public');

        // Here you would typically save to a KycDocument model
        // For now, just return success

        return response()->json([
            'success' => true,
            'message' => 'KYC document uploaded successfully',
            'data' => [
                'document_type' => $request->document_type,
                'file_path' => $path,
                'uploaded_at' => now(),
            ]
        ], 201);
    }

    /**
     * Get KYC status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        // Mock status - in real app, check KycDocument model
        $status = [
            'overall_status' => 'pending',
            'documents' => [
                'id_card' => 'approved',
                'utility_bill' => 'pending',
                'bank_statement' => 'rejected',
            ]
        ];

        return response()->json(['success' => true, 'data' => $status]);
    }

    /**
     * Get user's KYC documents
     */
    public function documents(Request $request): JsonResponse
    {
        $user = $request->user();

        // Mock documents - in real app, query KycDocument model
        $documents = [
            [
                'id' => 1,
                'document_type' => 'id_card',
                'file_path' => 'kyc/1/id_card.pdf',
                'status' => 'approved',
                'uploaded_at' => '2024-01-15T10:00:00Z',
            ]
        ];

        return response()->json(['success' => true, 'data' => $documents]);
    }
}