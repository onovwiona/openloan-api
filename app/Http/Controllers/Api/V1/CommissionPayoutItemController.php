<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommissionPayoutItem;
use Illuminate\Http\Request;

class CommissionPayoutItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $items = CommissionPayoutItem::with(['user', 'batch', 'commissionEvent'])
            ->when($request->has('batch_id'), function ($query) use ($request) {
                $query->where('batch_id', $request->batch_id);
            })
            ->when($request->has('user_id'), function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            })
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($items);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'batch_id' => 'required|exists:commission_payout_batches,id',
            'user_id' => 'required|exists:users,id',
            'commission_event_id' => 'nullable|exists:commission_events,id',
            'amount' => 'required|numeric|min:0',
            'status' => 'sometimes|in:pending,paid,failed',
            'notes' => 'nullable|string',
            'paid_at' => 'nullable|date',
        ]);

        $item = CommissionPayoutItem::create($validated);

        return response()->json([
            'message' => 'Commission payout item created successfully',
            'data' => $item->load(['user', 'batch', 'commissionEvent'])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(CommissionPayoutItem $commissionPayoutItem)
    {
        return response()->json([
            'data' => $commissionPayoutItem->load(['user', 'batch', 'commissionEvent'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CommissionPayoutItem $commissionPayoutItem)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:pending,paid,failed',
            'notes' => 'nullable|string',
            'paid_at' => 'nullable|date',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'paid' && !$commissionPayoutItem->paid_at) {
            $validated['paid_at'] = now();
        }

        $commissionPayoutItem->update($validated);

        return response()->json([
            'message' => 'Commission payout item updated successfully',
            'data' => $commissionPayoutItem->load(['user', 'batch', 'commissionEvent'])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CommissionPayoutItem $commissionPayoutItem)
    {
        $commissionPayoutItem->delete();

        return response()->json([
            'message' => 'Commission payout item deleted successfully'
        ]);
    }

    /**
     * Mark item as paid.
     */
    public function markPaid(CommissionPayoutItem $commissionPayoutItem)
    {
        $commissionPayoutItem->update([
            'status' => 'paid',
            'paid_at' => now()
        ]);

        return response()->json([
            'message' => 'Commission payout item marked as paid',
            'data' => $commissionPayoutItem
        ]);
    }
}