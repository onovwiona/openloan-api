<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SignupAttempt;
use Illuminate\Http\Request;

class SignupAttemptController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $attempts = SignupAttempt::with('user')
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->has('ip_address'), function ($query) use ($request) {
                $query->where('ip_address', $request->ip_address);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($attempts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email',
            'ip_address' => 'nullable|string|max:45',
            'user_agent' => 'nullable|string',
            'status' => 'sometimes|in:pending,verified,failed',
            'failure_reason' => 'nullable|string',
        ]);

        $attempt = SignupAttempt::create($validated);

        return response()->json([
            'message' => 'Signup attempt created successfully',
            'data' => $attempt->load('user')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(SignupAttempt $signupAttempt)
    {
        return response()->json([
            'data' => $signupAttempt->load('user')
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SignupAttempt $signupAttempt)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:pending,verified,failed',
            'failure_reason' => 'nullable|string',
        ]);

        $signupAttempt->update($validated);

        return response()->json([
            'message' => 'Signup attempt updated successfully',
            'data' => $signupAttempt
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SignupAttempt $signupAttempt)
    {
        $signupAttempt->delete();

        return response()->json([
            'message' => 'Signup attempt deleted successfully'
        ]);
    }
}