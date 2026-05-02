<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// User & Authentication controllers
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\CustomerProfileController;
use App\Http\Controllers\Api\V1\EmployeeProfileController;
use App\Http\Controllers\Api\V1\CustomerAttributionController;

// Referral controllers
use App\Http\Controllers\Api\V1\ReferralCodeController;
use App\Http\Controllers\Api\V1\ReferralEdgeController;
use App\Http\Controllers\Api\V1\ReferralPathController;

// Signup & Security controllers
use App\Http\Controllers\Api\V1\SignupAttemptController;
use App\Http\Controllers\Api\V1\FraudFlagController;

// Commission controllers
use App\Http\Controllers\Api\V1\CommissionRuleController;
use App\Http\Controllers\Api\V1\CommissionEventController;
use App\Http\Controllers\Api\V1\CommissionPayoutBatchController;
use App\Http\Controllers\Api\V1\CommissionPayoutItemController;

// Audit controller
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;

// NEW MODULES
use App\Http\Controllers\Api\V1\Ledger\LedgerController;
use App\Http\Controllers\Api\V1\Account\AccountController;
use App\Http\Controllers\Api\V1\Loan\LoanController;
use App\Http\Controllers\Api\V1\KycController;


/*
|--------------------------------------------------------------------------
| API V1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Authentication Routes
    |--------------------------------------------------------------------------
    */

    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login');

    /*
    |--------------------------------------------------------------------------
    | Authenticated Routes (all require JWT authentication)
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:api')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Auth Management
        |--------------------------------------------------------------------------
        */

        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
        Route::get('me', [AuthController::class, 'me'])->name('me');

        /*
        |--------------------------------------------------------------------------
        | Super Admin / Admin Only
        |--------------------------------------------------------------------------
        */

        Route::middleware('role:admin')->group(function () {

            // User Management
            Route::apiResource('users', UserController::class);
            Route::get('users/{user}/roles', [UserController::class, 'roles'])
                ->name('users.roles.index');
            Route::post('users/{user}/roles', [UserController::class, 'assignRoles'])
                ->name('users.roles.store');
            Route::get('users/{user}/customer-profile', [UserController::class, 'customerProfile'])
                ->name('users.customer-profile.show');
            Route::get('users/{user}/employee-profile', [UserController::class, 'employeeProfile'])
                ->name('users.employee-profile.show');

            // Role Management
            Route::apiResource('roles', RoleController::class);
            Route::get('roles/{role}/users', [RoleController::class, 'users'])
                ->name('roles.users.index');

            // Audit Logs
            Route::apiResource('audit-logs', AuditLogController::class);
            Route::get('audit-logs/entity/{entityType}/{entityId}', [AuditLogController::class, 'forEntity'])
                ->name('audit.entity');
            Route::get('audit-logs/user/{userId}', [AuditLogController::class, 'byUser'])
                ->name('audit.user');
        });


        /*
        |--------------------------------------------------------------------------
        | Staff / Office / Secretary
        |--------------------------------------------------------------------------
        */

        Route::middleware('role:admin|staff|office|secretary')->group(function () {

            Route::apiResource('customer-profiles', CustomerProfileController::class);
            Route::apiResource('employee-profiles', EmployeeProfileController::class);
            Route::apiResource('customer-attributions', CustomerAttributionController::class);

            Route::get(
                'customer-attributions/by-source-user/{sourceUserId}',
                [CustomerAttributionController::class, 'bySourceUser']
            )->name('customer-attributions.by-source-user');
        });


        /*
        |--------------------------------------------------------------------------
        | Marketers
        |--------------------------------------------------------------------------
        */

        Route::middleware('role:admin|marketer')->group(function () {

            Route::apiResource('referral-codes', ReferralCodeController::class);
            Route::apiResource('referral-edges', ReferralEdgeController::class);
            Route::apiResource('referral-paths', ReferralPathController::class);

            Route::post(
                'referral-codes/validate',
                [ReferralCodeController::class, 'validateCode']
            )->name('referral-codes.validate');

            Route::get(
                'referral-edges/{userId}/referrals',
                [ReferralEdgeController::class, 'referrals']
            )->name('referral-edges.referrals');

            Route::get(
                'referral-edges/{userId}/referrer',
                [ReferralEdgeController::class, 'referrer']
            )->name('referral-edges.referrer');
        });


        /*
        |--------------------------------------------------------------------------
        | Risk / Fraud / Compliance
        |--------------------------------------------------------------------------
        */

        Route::middleware('role:admin|auditor|staff')->group(function () {

            Route::apiResource('signup-attempts', SignupAttemptController::class);
            Route::apiResource('fraud-flags', FraudFlagController::class);

            Route::post(
                'fraud-flags/{fraudFlag}/resolve',
                [FraudFlagController::class, 'resolve']
            )->name('fraud-flags.resolve');
        });


        /*
        |--------------------------------------------------------------------------
        | Finance / Commission / Payouts - Auditor Tasks
        |--------------------------------------------------------------------------
        */

        Route::middleware('role:auditor')->group(function () {

            Route::apiResource('commission-rules', CommissionRuleController::class);
            Route::apiResource('commission-events', CommissionEventController::class);
            Route::apiResource('commission-payout-batches', CommissionPayoutBatchController::class);
            Route::apiResource('commission-payout-items', CommissionPayoutItemController::class);

            // Rules
            Route::post(
                'commission-rules/{commissionRule}/activate',
                [CommissionRuleController::class, 'activate']
            )->name('commission-rules.activate');

            Route::post(
                'commission-rules/{commissionRule}/deactivate',
                [CommissionRuleController::class, 'deactivate']
            )->name('commission-rules.deactivate');

            // Events
            Route::post(
                'commission-events/{commissionEvent}/approve',
                [CommissionEventController::class, 'approve']
            )->name('commission-events.approve');

            Route::post(
                'commission-events/{commissionEvent}/mark-paid',
                [CommissionEventController::class, 'markPaid']
            )->name('commission-events.mark-paid');

            // Batches
            Route::post(
                'commission-payout-batches/{commissionPayoutBatch}/process',
                [CommissionPayoutBatchController::class, 'process']
            )->name('commission-payout-batches.process');

            Route::post(
                'commission-payout-batches/{commissionPayoutBatch}/complete',
                [CommissionPayoutBatchController::class, 'complete']
            )->name('commission-payout-batches.complete');

            // Items
            Route::post(
                'commission-payout-items/{commissionPayoutItem}/mark-paid',
                [CommissionPayoutItemController::class, 'markPaid']
            )->name('commission-payout-items.mark-paid');
        });


        /*
        |--------------------------------------------------------------------------
        | LEDGER MODULE (Double-Entry Accounting) - Auditor Tasks
        |--------------------------------------------------------------------------
        */

        Route::middleware('role:auditor')->group(function () {

            // Ledger Accounts
            Route::get('ledgers', [LedgerController::class, 'index'])->name('ledgers.index');
            Route::post('ledgers', [LedgerController::class, 'store'])->name('ledgers.store');
            Route::get('ledgers/{id}', [LedgerController::class, 'show'])->name('ledgers.show');
            Route::put('ledgers/{id}', [LedgerController::class, 'update'])->name('ledgers.update');
            Route::get('ledgers/{id}/transactions', [LedgerController::class, 'transactions'])->name('ledgers.transactions');
            Route::get('ledgers/{id}/statement', [LedgerController::class, 'statement'])->name('ledgers.statement');

            // Trial Balance
            Route::get('ledger/trial-balance', [LedgerController::class, 'trialBalance'])->name('ledger.trial-balance');

            // General Ledger
            Route::get('ledger/gl', [LedgerController::class, 'generalLedger'])->name('ledger.gl');

            // Journal Entries
            Route::get('ledger/journals', [LedgerController::class, 'journals'])->name('ledger.journals.index');
            Route::post('ledger/journals', [LedgerController::class, 'createJournal'])->name('ledger.journals.store');
            Route::get('ledger/journals/{id}', [LedgerController::class, 'showJournal'])->name('ledger.journals.show');
            Route::post('ledger/journals/{id}/reverse', [LedgerController::class, 'reverseJournal'])->name('ledger.journals.reverse');

            // Day Closing
            Route::post('ledger/close-day', [LedgerController::class, 'closeDay'])->name('ledger.close-day');
            Route::get('ledger/close-day', [LedgerController::class, 'checkDayClosed'])->name('ledger.check-day-closed');
        });


        /*
        |--------------------------------------------------------------------------
        | ACCOUNTS MODULE (Customer Accounts)
        |--------------------------------------------------------------------------
        */

        // Account Types (read for all authenticated)
        Route::get('account-types', [AccountController::class, 'types'])->name('account-types.index');
        Route::get('account-types/{id}', [AccountController::class, 'showType'])->name('account-types.show');

        Route::middleware('role:admin|auditor')->group(function () {
            Route::post('account-types', [AccountController::class, 'createType'])->name('account-types.store');
        });

        // Accounts (admin/auditor can manage)
        Route::middleware('role:admin|auditor|staff')->group(function () {

            Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
            Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
            Route::get('accounts/{id}', [AccountController::class, 'show'])->name('accounts.show');

            // Credit/Debit operations
            Route::post('accounts/{id}/credit', [AccountController::class, 'credit'])->name('accounts.credit');
            Route::post('accounts/{id}/debit', [AccountController::class, 'debit'])->name('accounts.debit');
            Route::post('accounts/{id}/transfer', [AccountController::class, 'transfer'])->name('accounts.transfer');

            // Account operations
            Route::get('accounts/{id}/transactions', [AccountController::class, 'transactions'])->name('accounts.transactions');
            Route::get('accounts/{id}/statement', [AccountController::class, 'statement'])->name('accounts.statement');
            Route::post('accounts/{id}/freeze', [AccountController::class, 'freeze'])->name('accounts.freeze');
            Route::post('accounts/{id}/unfreeze', [AccountController::class, 'unfreeze'])->name('accounts.unfreeze');
            Route::post('accounts/{id}/close', [AccountController::class, 'close'])->name('accounts.close');
        });

        // User's own accounts (customer self-service)
        Route::middleware('role:customer')->group(function () {
            Route::get('users/{user_id}/accounts', [AccountController::class, 'userAccounts'])->name('user.accounts.index');
            Route::get('users/{user_id}/accounts/{id}', [AccountController::class, 'userAccount'])->name('user.accounts.show');
            Route::get('users/{user_id}/accounts/{id}/statement', [AccountController::class, 'userAccountStatement'])->name('user.accounts.statement');
        });


        /*
        |--------------------------------------------------------------------------
        | LOAN MODULE (Loan Products, Applications, Loans)
        |--------------------------------------------------------------------------
        */

        // Loan Products (read for all authenticated)
        Route::get('loan-products', [LoanController::class, 'products'])->name('loan-products.index');
        Route::get('loan-products/{id}', [LoanController::class, 'showProduct'])->name('loan-products.show');

        Route::middleware('role:admin|auditor')->group(function () {
            Route::post('loan-products', [LoanController::class, 'createProduct'])->name('loan-products.store');
        });

        // Loan Applications
        Route::middleware('role:admin|auditor|staff')->group(function () {

            Route::get('loan-applications', [LoanController::class, 'applications'])->name('loan-applications.index');
            Route::get('loan-applications/{id}', [LoanController::class, 'showApplication'])->name('loan-applications.show');

            // Approve/Reject
            Route::post('loan-applications/{id}/approve', [LoanController::class, 'approve'])->name('loan-applications.approve');
            Route::post('loan-applications/{id}/reject', [LoanController::class, 'reject'])->name('loan-applications.reject');
        });

        // Loans management
        Route::middleware('role:admin|auditor')->group(function () {

            Route::get('loans', [LoanController::class, 'index'])->name('loans.index');
            Route::get('loans/{id}', [LoanController::class, 'show'])->name('loans.show');

            // Loan operations
            Route::post('loans/{id}/disburse', [LoanController::class, 'disburse'])->name('loans.disburse');
            Route::post('loans/{id}/repay', [LoanController::class, 'repay'])->name('loans.repay');
            Route::get('loans/{id}/schedule', [LoanController::class, 'schedule'])->name('loans.schedule');
            Route::post('loans/{id}/restructure', [LoanController::class, 'restructure'])->name('loans.restructure');
            Route::post('loans/{id}/writeoff', [LoanController::class, 'writeoff'])->name('loans.writeoff');
        });

        // Customer: Create/submit applications
        Route::middleware('role:customer')->group(function () {
            Route::post('loan-applications', [LoanController::class, 'createApplication'])->name('loan-applications.store');
            Route::post('loan-applications/{id}/submit', [LoanController::class, 'submitApplication'])->name('loan-applications.submit');
            Route::post('loan-applications/{id}/cancel', [LoanController::class, 'cancelApplication'])->name('loan-applications.cancel');

            // User's loans
            Route::get('users/{user_id}/loan-applications/', [LoanController::class, 'userApplications'])->name('user.loan-applications.index');
            Route::get('users/{user_id}/loan-applications/{id}', [LoanController::class, 'userApplicationDetail'])->name('user.loan-applications.show');
            Route::get('users/{user_id}/loans/', [LoanController::class, 'userLoans'])->name('user.loans.index');
            Route::get('users/{user_id}/loans/{id}', [LoanController::class, 'userLoanDetail'])->name('user.loans.show');
        });


        /*
        |--------------------------------------------------------------------------
        | Customer Self-Service
        |--------------------------------------------------------------------------
        */

        Route::middleware('role:customer')->group(function () {

            Route::get('/my-profile', function () {
                return response()->json([
                    'success' => true,
                    'message' => 'Profile retrieved successfully',
                    'data' => request()->user()->load('customerProfile'),
                ]);
            })->name('customer.profile');

            // KYC Uploads
            Route::post('kyc/upload', [KycController::class, 'upload'])->name('kyc.upload');
            Route::get('kyc/status', [KycController::class, 'status'])->name('kyc.status');
            Route::get('kyc/documents', [KycController::class, 'documents'])->name('kyc.documents');
        });

    });
});

