<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommissionRule;
use Illuminate\Http\Request;

class CommissionRuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $rules = CommissionRule::with('creator')
            ->when($request->has('name'), function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->name}%");
            })
            ->when($request->has('is_active'), function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->when($request->has('commission_type'), function ($query) use ($request) {
                $query->where('commission_type', $request->commission_type);
            })
            ->paginate($request->get('per_page', 15));

        return response()->json($rules);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'commission_type' => 'required|in:referral,direct,override,team',
            'rate' => 'required|numeric|min:0|max:100',
            'tier_from' => 'nullable|integer|min:0',
            'tier_to' => 'nullable|integer|min:0',
            'min_volume' => 'nullable|integer|min:0',
            'max_payout' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'created_by' => 'nullable|exists:users,id',
        ]);

        $rule = CommissionRule::create($validated);

        return response()->json([
            'message' => 'Commission rule created successfully',
            'data' => $rule->load('creator')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(CommissionRule $commissionRule)
    {
        return response()->json([
            'data' => $commissionRule->load('creator')
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CommissionRule $commissionRule)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'commission_type' => 'sometimes|in:referral,direct,override,team',
            'rate' => 'sometimes|numeric|min:0|max:100',
            'tier_from' => 'nullable|integer|min:0',
            'tier_to' => 'nullable|integer|min:0',
            'min_volume' => 'nullable|integer|min:0',
            'max_payout' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'created_by' => 'nullable|exists:users,id',
        ]);

        $commissionRule->update($validated);

        return response()->json([
            'message' => 'Commission rule updated successfully',
            'data' => $commissionRule->load('creator')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CommissionRule $commissionRule)
    {
        $commissionRule->delete();

        return response()->json([
            'message' => 'Commission rule deleted successfully'
        ]);
    }

    /**
     * Activate a commission rule.
     */
    public function activate(CommissionRule $commissionRule)
    {
        $commissionRule->update(['is_active' => true]);

        return response()->json([
            'message' => 'Commission rule activated',
            'data' => $commissionRule
        ]);
    }

    /**
     * Deactivate a commission rule.
     */
    public function deactivate(CommissionRule $commissionRule)
    {
        $commissionRule->update(['is_active' => false]);

        return response()->json([
            'message' => 'Commission rule deactivated',
            'data' => $commissionRule
        ]);
    }
}