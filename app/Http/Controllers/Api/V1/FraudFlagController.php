<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FraudFlag;
use Illuminate\Http\Request;

class FraudFlagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $flags = FraudFlag::with('user')
            ->when($request->has('flag_type'), function ($query) use ($request) {
                $query->where('flag_type', $request->flag_type);
            })
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->has('severity'), function ($query) use ($request) {
                $query->where('severity', $request->severity);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($flags);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'flag_type' => 'required|string|max:255',
            'description' => 'required|string',
            'severity' => 'required|in:low,medium,high,critical',
            'status' => 'sometimes|in:pending,investigated,resolved,dismissed',
            'evidence' => 'nullable|array',
        ]);

        $flag = FraudFlag::create($validated);

        return response()->json([
            'message' => 'Fraud flag created successfully',
            'data' => $flag->load('user')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(FraudFlag $fraudFlag)
    {
        return response()->json([
            'data' => $fraudFlag->load('user')
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FraudFlag $fraudFlag)
    {
        $validated = $request->validate([
            'flag_type' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'severity' => 'sometimes|in:low,medium,high,critical',
            'status' => 'sometimes|in:pending,investigated,resolved,dismissed',
            'evidence' => 'nullable|array',
            'resolution_notes' => 'nullable|string',
        ]);

        $fraudFlag->update($validated);

        return response()->json([
            'message' => 'Fraud flag updated successfully',
            'data' => $fraudFlag->load('user')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FraudFlag $fraudFlag)
    {
        $fraudFlag->delete();

        return response()->json([
            'message' => 'Fraud flag deleted successfully'
        ]);
    }

    /**
     * Resolve a fraud flag.
     */
    public function resolve(Request $request, FraudFlag $fraudFlag)
    {
        $request->validate([
            'resolution_notes' => 'required|string',
        ]);

        $fraudFlag->update([
            'status' => 'resolved',
            'resolution_notes' => $request->resolution_notes,
        ]);

        return response()->json([
            'message' => 'Fraud flag resolved successfully',
            'data' => $fraudFlag->load('user')
        ]);
    }
}