<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Force API requests to expect and receive JSON responses.
     * This prevents redirect responses on authentication failures.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $response = $next($request);

        if (!$response instanceof \Illuminate\Http\JsonResponse) {
            $response->headers->set('Content-Type', 'application/json');
        }

        return $response;
    }
}

