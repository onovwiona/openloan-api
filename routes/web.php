<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Fallback login route for API authentication failures
// Ensures JSON response instead of route-not-found errors
Route::get('/login', function (Request $request) {
    return response()->json([
        'success' => false,
        'message' => 'Unauthenticated.',
        'errors' => ['auth' => ['You must be logged in to access this resource.']],
    ], 401);
})->name('login');

