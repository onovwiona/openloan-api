<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use App\Models\Account;
use App\Policies\AccountPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Gate::policy(Account::class, AccountPolicy::class);

        // Route model binding constraints
        \Illuminate\Support\Facades\Route::model('application', \App\Models\LoanApplication::class);
        
        // Constrain LoanApplication binding to ensure it belongs to the correct user
        \Illuminate\Support\Facades\Route::bind('application', function ($value, $route) {
            $userParam = $route->parameter('user');
            
            // If {user} parameter exists (admin routes), check against it
            if ($userParam) {
                $userId = is_object($userParam) ? $userParam->id : $userParam;
                return \App\Models\LoanApplication::where('id', $value)
                    ->where('customer_id', $userId)
                    ->firstOrFail();
            }
            
            // For customer routes without {user} parameter, check against authenticated user
            $authenticatedUser = \Illuminate\Support\Facades\Auth::user();
            if ($authenticatedUser) {
                return \App\Models\LoanApplication::where('id', $value)
                    ->where('customer_id', $authenticatedUser->id)
                    ->firstOrFail();
            }
            
            // Fallback (should not reach here if route is protected by auth middleware)
            return \App\Models\LoanApplication::findOrFail($value);
        });
    }
}

