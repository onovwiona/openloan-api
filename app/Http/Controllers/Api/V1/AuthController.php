<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Register a new user and return a JWT token.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        // Assign default customer role if it exists
        $customerRole = \App\Models\Role::where('name', 'customer')->first();
        if ($customerRole) {
            $user->assignRole($customerRole);
        }

        $token = JWTAuth::fromUser($user);

        return $this->successResponse([
            'user' => $user->load('roles'),
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ], 'User registered successfully', 201);
    }

    /**
     * Log in an existing user and return a JWT token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required_without:phone|nullable|email',
            'phone' => 'required_without:email|nullable|string',
            'password' => 'required|string',
        ]);

        $credentials = [];

        if ($request->filled('email')) {
            $credentials['email'] = $request->email;
        } elseif ($request->filled('phone')) {
            $credentials['phone'] = $request->phone;
        }

        $credentials['password'] = $request->password;

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return $this->errorResponse('Invalid credentials.', 401, [
                    'auth' => ['The provided credentials are incorrect.'],
                ]);
            }
        } catch (JWTException $e) {
            return $this->errorResponse('Could not create token.', 500, [
                'token' => ['Server error while generating authentication token.'],
            ]);
        }

        $user = JWTAuth::user();

        // Update last login timestamp
        $user->update(['last_login_at' => now()]);

        return $this->successResponse([
            'user' => $user->load('roles'),
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ], 'Login successful');
    }

    /**
     * Log the user out (invalidate the token).
     */
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return $this->successResponse(null, 'Successfully logged out');
        } catch (JWTException $e) {
            return $this->errorResponse('Failed to logout, please try again.', 500, [
                'token' => ['Server error while invalidating token.'],
            ]);
        }
    }

    /**
     * Refresh a token.
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());

            return $this->successResponse([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
            ], 'Token refreshed successfully');
        } catch (JWTException $e) {
            return $this->errorResponse('Could not refresh token.', 401, [
                'token' => ['The token could not be refreshed. Please log in again.'],
            ]);
        }
    }

    /**
     * Get the authenticated user.
     */
    public function me(): JsonResponse
    {
        $user = JWTAuth::user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated.', 401, [
                'auth' => ['No authenticated user found.'],
            ]);
        }

        return $this->successResponse(
            $user->load(['roles', 'customerProfile', 'employeeProfile', 'referralCode']),
            'Authenticated user retrieved successfully'
        );
    }
}

