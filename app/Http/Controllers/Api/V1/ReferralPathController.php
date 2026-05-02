<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ReferralPath;
use Illuminate\Http\Request;

class ReferralPathController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $paths = ReferralPath::with(['user', 'edges'])
            ->when($request->has('user_id'), function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            })
            ->paginate($request->get('per_page', 15));

        return response()->json($paths);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'depth' => 'required|integer|min:1',
        ]);

        $path = ReferralPath::create($validated);

        return response()->json([
            'message' => 'Referral path created successfully',
            'data' => $path->load(['user', 'edges'])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ReferralPath $referralPath)
    {
        return response()->json([
            'data' => $referralPath->load(['user', 'edges'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReferralPath $referralPath)
    {
        $validated = $request->validate([
            'depth' => 'sometimes|integer|min:1',
        ]);

        $referralPath->update($validated);

        return response()->json([
            'message' => 'Referral path updated successfully',
            'data' => $referralPath->load(['user', 'edges'])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReferralPath $referralPath)
    {
        $referralPath->delete();

        return response()->json([
            'message' => 'Referral path deleted successfully'
        ]);
    }
}