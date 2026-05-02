<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CommissionPayoutBatch;

class CommissionPayoutBatchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $batches = CommissionPayoutBatch::with('creator')
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->has('from_date'), function ($query) use ($request) {
                $query->where('from_date', '>=', $request->from_date);
            })
            ->when($request->has('to_date'), function ($query) use ($request) {
                $query->where('to_date', '<=', $request->to_date);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($batches);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after:from_date',
            'total_amount' => 'required|numeric|min:0',
            'status' => 'sometimes|in:draft,processing,completed,failed',
            'notes' => 'nullable|string',
            'created_by' => 'nullable|exists:users,id',
        ]);

        $batch = CommissionPayoutBatch::create($validated);

        return response()->json([
            'message' => 'Commission payout batch created successfully',
            'data' => $batch->load('creator')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(CommissionPayoutBatch $CommissionPayoutBatch)
    {
        return response()->json([
            'data' => $CommissionPayoutBatch->load(['creator', 'items'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CommissionPayoutBatch $CommissionPayoutBatch)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:draft,processing,completed,failed',
            'notes' => 'nullable|string',
            'processed_at' => 'nullable|date',
        ]);

        $CommissionPayoutBatch->update($validated);

        return response()->json([
            'message' => 'Commission payout batch updated successfully',
            'data' => $CommissionPayoutBatch->load('creator')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CommissionPayoutBatch $CommissionPayoutBatch)
    {
        $CommissionPayoutBatch->delete();

        return response()->json([
            'message' => 'Commission payout batch deleted successfully'
        ]);
    }

    /**
     * Process a payout batch.
     */
    public function process(CommissionPayoutBatch $CommissionPayoutBatch)
    {
        $CommissionPayoutBatch->update([
            'status' => 'processing',
        ]);

        return response()->json([
            'message' => 'Commission payout batch processing',
            'data' => $CommissionPayoutBatch
        ]);
    }

    /**
     * Complete a payout batch.
     */
    public function complete(CommissionPayoutBatch $CommissionPayoutBatch)
    {
        $CommissionPayoutBatch->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Commission payout batch completed',
            'data' => $CommissionPayoutBatch
        ]);
    }
}