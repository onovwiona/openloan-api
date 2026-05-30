<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSingletons([
        \Illuminate\Contracts\Console\Kernel::class => \App\Console\Kernel::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);

        // Force JSON responses on all API routes to prevent redirects on auth failures
        $middleware->prependToGroup('api', \App\Http\Middleware\ForceJsonResponse::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Helper to determine if the request expects JSON
        $expectsJson = function (Request $request): bool {
            return $request->is('api/*') || $request->expectsJson();
        };

        // 401 — Unauthenticated (AuthenticationException)
        $exceptions->renderable(function (AuthenticationException $e, Request $request) use ($expectsJson) {
            if ($expectsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'errors' => ['auth' => ['You must be logged in to access this resource.']],
                ], 401);
            }
        });

        // 401 — JWT Token Invalid / Expired / Missing
        $exceptions->renderable(function (TokenInvalidException $e, Request $request) use ($expectsJson) {
            if ($expectsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token.',
                    'errors' => ['token' => ['The provided authentication token is invalid.']],
                ], 401);
            }
        });

        $exceptions->renderable(function (TokenExpiredException $e, Request $request) use ($expectsJson) {
            if ($expectsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token expired.',
                    'errors' => ['token' => ['The authentication token has expired. Please refresh or log in again.']],
                ], 401);
            }
        });

        $exceptions->renderable(function (JWTException $e, Request $request) use ($expectsJson) {
            if ($expectsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token error.',
                    'errors' => ['token' => [$e->getMessage() ?: 'An error occurred with the authentication token.']],
                ], 401);
            }
        });

        // 403 — Forbidden (AccessDeniedHttpException, role/permission failures)
        $exceptions->renderable(function (AccessDeniedHttpException $e, Request $request) use ($expectsJson) {
            if ($expectsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden. You do not have permission to access this resource.',
                    'errors' => ['access' => [$e->getMessage() ?: 'Access denied.']],
                ], 403);
            }
        });

        // 404 — Not Found (NotFoundHttpException, ModelNotFoundException)
        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) use ($expectsJson) {
            if ($expectsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                    'errors' => ['route' => [$e->getMessage() ?: 'The requested resource could not be found.']],
                    'docs_url' => request()->getSchemeAndHttpHost() . '/docs.html',

                ], 404);
            }
        });

        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) use ($expectsJson) {
            if ($expectsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                    'errors' => ['model' => [$e->getMessage() ?: 'The requested record could not be found.']],
                    'docs_url' => request()->getSchemeAndHttpHost() . '/docs.html',

                ], 404);
            }
        });

        // 405 — Method Not Allowed
        $exceptions->renderable(function (MethodNotAllowedHttpException $e, Request $request) use ($expectsJson) {
            if ($expectsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Method not allowed.',
                    'errors' => ['method' => [$e->getMessage() ?: 'The HTTP method is not allowed for this route.']],
                ], 405);
            }
        });

        // 422 — Validation Errors
        $exceptions->renderable(function (ValidationException $e, Request $request) use ($expectsJson) {
            if ($expectsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // 500 — Generic Server Error (fallback for API)
        $exceptions->renderable(function (Throwable $e, Request $request) use ($expectsJson) {
            if ($expectsJson($request)) {
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                if ($status < 400 || $status >= 600) {
                    $status = 500;
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Server error.',
                    'errors' => ['server' => [app()->environment('local', 'development') ? $e->getMessage() : 'An unexpected error occurred.']],
                ], $status);
            }
        });
    })->create();

