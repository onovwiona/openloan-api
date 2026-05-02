<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $roles = array_map('trim', explode('|', $roles));

        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => ['auth' => ['You must be logged in to access this resource.']],
            ], 401);
        }

        if (!$request->user()->hasAnyRole($roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. You do not have the required role.',
                'errors' => ['role' => ['Required one of: ' . implode(', ', $roles)]],
            ], 403);
        }

        return $next($request);
    }
}
