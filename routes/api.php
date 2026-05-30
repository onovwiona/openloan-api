<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Account\AccountController;
use App\Http\Controllers\Api\V1\CustomerProfileController;
use App\Http\Controllers\Api\V1\Loan\LoanController;
use App\Http\Controllers\Api\V1\Admin\LoanDocumentTypeController;
use App\Http\Controllers\Api\V1\KycController;
use App\Http\Middleware\RoleMiddleware;

Route::middleware('api')->prefix('v1')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:api')->group(function () {
        Route::post('users/{user}/accounts', [AccountController::class, 'storeAccountForUser']);
        Route::get('users/{user}/accounts', [AccountController::class, 'userAccounts']);
        Route::get('users/{user}/accounts/{account}', [AccountController::class, 'userAccount']);
        Route::get('account-types', [AccountController::class, 'types']);
        Route::get('account-types/{id}', [AccountController::class, 'showType']);
        Route::get('loan-products/{id}', [LoanController::class, 'showProduct']);
    });

    Route::middleware('auth:api', 'role:customer')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('loan-products', [LoanController::class, 'products']);
        Route::get('user/accounts', [AccountController::class, 'myAccounts']);
        Route::get('user/accounts/{account}', [AccountController::class, 'myAccount']);
        Route::get('user/accounts/{account}/statements', [AccountController::class, 'myAccountStatement']);
        Route::get('user/accounts/{account}/transactions', [AccountController::class, 'myAccountTransactions']);
        Route::post('user/accounts', [AccountController::class, 'createMyAccount']);
        Route::post('user/loan-applications', [LoanController::class, 'createMyApplication']);
        Route::post('loan-applications', [LoanController::class, 'createApplication']);
        Route::post('user/loan-applications/{id}/submit', [LoanController::class, 'submitMyApplication']);
        Route::post('loan-applications/{id}/submit', [LoanController::class, 'submitApplication']);
        Route::get('user/loan-applications', [LoanController::class, 'myApplications']);
        Route::get('user/loan-applications/{id}', [LoanController::class, 'myApplicationDetail']);
        Route::post('user/loan-applications/{application}/collateral', [LoanController::class, 'uploadCollateral']);
        Route::post('user/loan-applications/{application}/collaterals', [LoanController::class, 'uploadCollateral']);
        Route::post('user/loan-applications/{application}/documents', [LoanController::class, 'uploadDocument']);
        Route::post('user/loan-applications/{application}/guarantors', [LoanController::class, 'addGuarantor']);
        Route::post('user/loan-applications/{application}/guarantors/{guarantor}/notes', [LoanController::class, 'uploadGuarantorNotes']);
        Route::get('user/loan-applications/{application}/documents', [LoanController::class, 'myApplicationDocuments']);
        Route::get('user/loan-applications/{application}/collaterals', [LoanController::class, 'myApplicationCollaterals']);
        Route::get('user/loan-applications/{application}/guarantors', [LoanController::class, 'myApplicationGuarantors']);
        Route::post('user/kyc/documents', [KycController::class, 'storeDocument']);
        Route::get('user/kyc/documents', [KycController::class, 'listDocuments']);
        Route::delete('user/kyc/documents/{document}', [KycController::class, 'deleteDocument']);
        Route::get('user/kyc/employment-profiles', [KycController::class, 'myEmploymentProfile']);
        Route::post('user/kyc/employment-profiles', [KycController::class, 'storeEmploymentProfile']);
        Route::patch('user/kyc/employment-profiles', [KycController::class, 'updateEmploymentProfile']);
        Route::post('user/kyc/employment-profiles/documents', [KycController::class, 'storeEmploymentDocuments']);
        Route::patch('user/kyc/employment-profiles/documents', [KycController::class, 'storeEmploymentDocuments']);
        Route::get('user/loans', [LoanController::class, 'userLoans']);
        Route::get('user/loans/{id}', [LoanController::class, 'userLoanDetail']);
        Route::get('user/loans/{id}/schedule', [LoanController::class, 'schedule']);
        Route::get('user/loans/{id}/schedules', [LoanController::class, 'userLoanSchedules']);
        Route::get('user/loans/{id}/repayments', [LoanController::class, 'userLoanRepayments']);
        Route::post('user/loans/{id}/repay', [LoanController::class, 'repay']);
        Route::post('user/loans/{id}/repayments', [LoanController::class, 'userLoanRepayment']);
        Route::get('user/loans/{id}/payoff-quote', [LoanController::class, 'userLoanPayoffQuote']);
        Route::post('user/loans/{id}/payoff', [LoanController::class, 'userLoanPayoff']);
        Route::post('users/{user}/kyc', [KycController::class, 'uploadForUser']);
        Route::post('user/kyc', [KycController::class, 'upload']);
        Route::post('user/kyc/passport-document', [KycController::class, 'uploadPassportDocument']);
        Route::post('user/kyc/id-card', [KycController::class, 'uploadIdCard']);
        Route::post('user/kyc/passport-photo', [KycController::class, 'uploadPassportPhoto']);
        Route::post('user/kyc/guarantor-form', [KycController::class, 'uploadGuarantorForm']);
        Route::get('user/kyc', [KycController::class, 'myKyc']);
        Route::get('user/customer-profile', [CustomerProfileController::class, 'myProfile']);
        Route::patch('user/customer-profile', [CustomerProfileController::class, 'updateMyProfile']);
    });

    Route::middleware('auth:api', 'role:admin|staff|secretary|marketer')->group(function () {
        Route::get('kyc', [KycController::class, 'index']);
        Route::get('kyc/{profile}', [KycController::class, 'showProfile']);
        Route::get('customers/{user}/kyc', [KycController::class, 'show']);
        Route::apiResource('customer-profiles', CustomerProfileController::class);
        Route::get('loan-applications', [LoanController::class, 'applications']);
        Route::get('loan-applications/{application}', [LoanController::class, 'showApplication']);
        Route::apiResource('accounts', AccountController::class)->only(['index', 'show']);
        Route::apiResource('loans', LoanController::class);
    });

    Route::middleware('auth:api', 'role:admin')->group(function () {
        Route::apiResource('accounts', AccountController::class)->except(['index', 'show']);
        Route::post('account-types', [AccountController::class, 'createType']);
        Route::patch('kyc/{profile}', [KycController::class, 'updateProfile']);
        Route::patch('kyc/{profile}/reject', [KycController::class, 'reject']);
        Route::patch('kyc/{profile}/employment-profile/verify', [KycController::class, 'verifyEmploymentProfile']);
        Route::patch('kyc/{profile}/employment-profile/reject', [KycController::class, 'rejectEmploymentProfile']);
        Route::patch('kyc/{profile}/employment-profile/under-review', [KycController::class, 'markEmploymentProfileUnderReview']);
        Route::delete('kyc/{profile}', [KycController::class, 'destroyProfile']);
    });

    Route::middleware('auth:api', 'role:admin')->group(function () {
        Route::post('loan-products', [LoanController::class, 'createProduct']);
        Route::apiResource('loan-document-types', LoanDocumentTypeController::class);
        Route::post('loan-products/{loanProduct}/document-types', [LoanDocumentTypeController::class, 'attachToProduct']);
        Route::delete('loan-products/{loanProduct}/document-types/{documentType}', [LoanDocumentTypeController::class, 'detachFromProduct']);
    });

    Route::middleware('auth:api', 'role:admin|staff')->group(function () {
        Route::get('users/{user}/kyc', [KycController::class, 'show']);
        Route::get('users/{user}/kyc/employment-profile', [KycController::class, 'showUserEmploymentProfile']);
        Route::post('users/{user}/kyc/employment-profile', [KycController::class, 'storeUserEmploymentProfile']);
        Route::patch('users/{user}/kyc/employment-profile', [KycController::class, 'updateUserEmploymentProfile']);
        Route::post('users/{user}/kyc/employment-profile/documents', [KycController::class, 'storeUserEmploymentDocuments']);
        Route::patch('kyc/{profile}/documents/{documentType}/verify', [KycController::class, 'verifyDocument']);
        Route::patch('kyc/{profile}/verify', [KycController::class, 'verify']);
        Route::patch('customer-profiles/{customer_profile}/kyc-status', [CustomerProfileController::class, 'changeKycStatus']);
        Route::get('users/{user}/loan-applications', [LoanController::class, 'userLoanApplications']);
        Route::get('users/{user}/loan-applications/{application}', [LoanController::class, 'userLoanApplicationDetail']);
        Route::post('users/{user}/loan-applications/{application}/approve', [LoanController::class, 'userApproveApplication']);
        Route::post('users/{user}/loan-applications/{application}/reject', [LoanController::class, 'userRejectApplication']);
        Route::post('users/{user}/loan-applications/{application}/disburse', [LoanController::class, 'userDisburseApplication']);
        Route::post('loan-applications/{id}/verify', [LoanController::class, 'verify']);
        Route::post('loan-applications/{id}/approve', [LoanController::class, 'approve']);
        Route::patch('loan-applications/{id}/payroll', [LoanController::class, 'updateApplicationPayroll']);
        Route::post('loan-applications/{id}/reject', [LoanController::class, 'rejectApplication']);
        Route::post('users/{user}/loan-applications/{id}/verify', [LoanController::class, 'verifyUserApplication']);
        Route::post('users/{user}/loan-applications/{id}/reject', [LoanController::class, 'rejectUserApplication']);
        Route::get('users/{user}/loan-applications/{application}/documents', [LoanController::class, 'userApplicationDocuments']);
        Route::patch('users/{user}/loan-applications/{application}/documents', [LoanController::class, 'updateApplicationDocumentStatus']);
        Route::post('users/{user}/loan-applications/{application}/documents/verify', [LoanController::class, 'verifyApplicationDocument']);
        Route::post('users/{user}/loan-applications/{application}/documents/reject', [LoanController::class, 'rejectApplicationDocument']);
        Route::post('users/{user}/loan-applications/{application}/documents/under-review', [LoanController::class, 'underReviewApplicationDocument']);
        Route::post('users/{user}/loan-applications/{application}/collaterals/verify', [LoanController::class, 'verifyApplicationCollateral']);
        Route::post('users/{user}/loan-applications/{application}/collaterals/reject', [LoanController::class, 'rejectApplicationCollateral']);
        Route::post('users/{user}/loan-applications/{application}/collaterals/under-review', [LoanController::class, 'underReviewApplicationCollateral']);
        Route::post('users/{user}/loan-applications/{application}/guarantors/verify', [LoanController::class, 'verifyApplicationGuarantor']);
        Route::post('users/{user}/loan-applications/{application}/guarantors/reject', [LoanController::class, 'rejectApplicationGuarantor']);
        Route::post('users/{user}/loan-applications/{application}/guarantors/under-review', [LoanController::class, 'underReviewApplicationGuarantor']);
        Route::post('loans/{id}/approve', [LoanController::class, 'approve']);
        Route::post('loans/{id}/reject', [LoanController::class, 'reject']);
        Route::post('loans/{id}/disburse', [LoanController::class, 'disburse']);
        Route::post('loans/{id}/repay', [LoanController::class, 'repay']);
        Route::post('loans/{id}/manual-repayment', [LoanController::class, 'manualRepayment']);
        Route::post('loans/{id}/payoff', [LoanController::class, 'payoff']);
        Route::post('loans/{id}/restructure', [LoanController::class, 'restructure']);
        Route::post('loans/{id}/writeoff', [LoanController::class, 'writeoff']);
        Route::post('admin/wallets/{walletId}/deposits', [LoanController::class, 'depositToWallet']);
        Route::post('admin/loans/{loanId}/repayments', [LoanController::class, 'adminLoanRepayment']);
    });

    Route::middleware('auth:api', 'role:admin')->group(function () {
        Route::post('accounts/{account}/credit', [AccountController::class, 'credit']);
        Route::post('accounts/{account}/debit', [AccountController::class, 'debit']);
    });
});

