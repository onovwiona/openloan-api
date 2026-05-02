<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommissionEvent;
use Illuminate\Http\Request;

class CommissionEventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $events = CommissionEvent::with(['beneficiary', 'sourceUser', 'rule', 'referralEdge'])
            ->when($request->has('beneficiary_user_id'), function ($query) use ($request) {
                $query->where('beneficiary_user_id', $request->beneficiary_user_id);
            })
            ->when($request->has('source_user_id'), function ($query) use ($request) {
                $query->where('source_user_id', $request->source_user_id);
            })
            ->when($request->has('rule_id'), function ($query) use ($request) {
                $query->where('rule_id', $request->rule_id);
            })
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->has('event_type'), function ($query) use ($request) {
                $query->where('event_type', $request->event_type);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($events);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'beneficiary_user_id' => 'required|exists:users,id',
            'source_user_id' => 'nullable|exists:users,id',
            'rule_id' => 'nullable|exists:commission_rules,id',
            'reference_type' => 'nullable|string|max:255',
            'reference_id' => 'nullable|numeric',
            'event_type' => 'required|in:signup,referral_bonus,performance_bonus,override',
            'base_amount' => 'nullable|numeric|min:0',
            'commission_amount' => 'required|numeric|min:0',
            'status' => 'sometimes|in:pending,approved,paid,rejected',
            'earned_at' => 'nullable|date',
            'approved_at' => 'nullable|date',
            'paid_at' => 'nullable|date',
        ]);

        $event = CommissionEvent::create($validated);

        return response()->json([
            'message' => 'Commission event created successfully',
            'data' => $event->load(['beneficiary', 'sourceUser', 'rule', 'referralEdge'])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(CommissionEvent $commissionEvent)
    {
        return response()->json([
            'data' => $commissionEvent->load(['beneficiary', 'sourceUser', 'rule', 'referralEdge'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CommissionEvent $commissionEvent)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:pending,approved,paid,rejected',
            'commission_amount' => 'sometimes|numeric|min:0',
            'paid_at' => 'nullable|date',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'paid' && !$commissionEvent->paid_at) {
            $validated['paid_at'] = now();
        }

        $commissionEvent->update($validated);

        return response()->json([
            'message' => 'Commission event updated successfully',
            'data' => $commissionEvent->load(['beneficiary', 'sourceUser', 'rule', 'referralEdge'])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CommissionEvent $commissionEvent)
    {
        $commissionEvent->delete();

        return response()->json([
            'message' => 'Commission event deleted successfully'
        ]);
    }

    /**
     * Approve a commission event.
     */
    public function approve(CommissionEvent $commissionEvent)
    {
        $commissionEvent->update(['status' => 'approved']);

        return response()->json([
            'message' => 'Commission event approved',
            'data' => $commissionEvent->load(['user', 'rule'])
        ]);
    }

    /**
     * Mark commission as paid.
     */
    public function markPaid(CommissionEvent $commissionEvent)
    {
        $commissionEvent->update([
            'status' => 'paid',
            'paid_at' => now()
        ]);

        return response()->json([
            'message' => 'Commission marked as paid',
            'data' => $commissionEvent->load(['user', 'rule'])
        ]);
    }
}