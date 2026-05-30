<?php

namespace App\Http\Controllers\Api\V1\Loan;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocument;
use App\Models\LoanCollateral;
use App\Models\LoanGuarantor;
use App\Models\Account;
use App\Models\KycDocument;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\Loan\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;

class LoanController extends Controller
{
    public function __construct(
        protected LoanService $loanService
    ) {}

    // ================== LOAN PRODUCTS ==================

    /**
     * GET /loan-products
     */
    public function products(Request $request): JsonResponse
    {
        $query = LoanProduct::query()
            ->when($request->active === 'false', fn($q) => $q->where('active', false))
            ->when(!$request->include_inactive, fn($q) => $q->active());

        $products = $query->orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $products]);
    }

    /**
     * GET /loan-products/{id}
     */
    public function showProduct(string $id): JsonResponse
    {
        $product = LoanProduct::findOrFail($id);
        return response()->json(['success' => true, 'data' => $product]);
    }

    /**
     * POST /loan-products
     */
    public function createProduct(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:20|unique:loan_products,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'requires_account' => 'nullable|boolean',
            'repayment_account_type_id' => 'nullable|uuid|exists:account_types,id',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|min:0',
            'interest_type' => 'required|in:flat,reducing',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'tenure_min_months' => 'required|integer|min:1',
            'tenure_max_months' => 'required|integer|min:1',
            'processing_fee' => 'nullable|numeric|min:0|max:100',
            'penalty_rate' => 'nullable|numeric|min:0|max:100',
            'insurance_fee' => 'nullable|numeric|min:0|max:100',
            'legal_fee' => 'nullable|numeric|min:0|max:100',
            'allow_early_repayment' => 'nullable|boolean',
            'early_repayment_penalty' => 'nullable|numeric|min:0|max:100',
            'requires_guarantor' => 'nullable|boolean',
            'min_guarantors' => 'nullable|integer|min:0',
            'requires_collateral' => 'nullable|boolean',
            'requires_passport' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $product = LoanProduct::create($request->validated());
        return response()->json(['success' => true, 'message' => 'Loan product created', 'data' => $product], 201);
    }

    // ================== LOAN APPLICATIONS ==================

    /**
     * GET /loan-applications
     */
    public function applications(Request $request): JsonResponse
    {
        $query = LoanApplication::with(['loanProduct', 'customer'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->customer_id, fn($q, $c) => $q->where('customer_id', $c))
            ->when($request->start_date, fn($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->end_date, fn($q, $d) => $q->where('created_at', '<=', $d));

        $applications = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 20);
        return response()->json(['success' => true, 'data' => $applications]);
    }

    /**
     * GET /loan-applications/{id}
     */
    public function showApplication(string $id): JsonResponse
    {
        $application = LoanApplication::with(['loanProduct', 'customer', 'guarantors', 'collaterals', 'documents'])
            ->findOrFail($id);
        return response()->json(['success' => true, 'data' => $application]);
    }

    /**
     * POST /loan-applications
     */
    public function createApplication(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:users,id',
            'loan_product_id' => 'required|uuid|exists:loan_products,id',
            'account_id' => 'nullable|uuid|exists:accounts,id',
            'requested_amount' => 'required|numeric|min:1',
            'requested_tenure' => 'required|integer|min:1',
            'repayment_plan' => 'nullable|in:monthly,weekly,quarterly,annually',
            'monthly_income' => 'nullable|numeric|min:0',
            'payroll_gross' => 'nullable|numeric|min:0',
            'payroll_net' => 'nullable|numeric|min:0',
            'employer_id_number' => 'nullable|string',
            'employment_status' => 'nullable|string',
            'purpose' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        try {
            // Prefer employment data from the customer's employment profile; fall back to request if missing
            $customer = User::findOrFail($request->customer_id);
            $employmentProfile = $customer->customerProfile?->employmentProfile;

            $employerIdNumber = $employmentProfile?->employer_id_number ?? $request->input('employer_id_number');
            $monthlyIncome = $employmentProfile?->monthly_income ?? $request->input('monthly_income');
            $payrollGross = $employmentProfile?->payroll_gross ?? $request->input('payroll_gross');
            $payrollNet = $employmentProfile?->payroll_net ?? $request->input('payroll_net');
            $employmentStatus = $employmentProfile?->employment_status ?? $request->input('employment_status');

            $application = $this->loanService->createApplication(
                $request->customer_id,
                $request->loan_product_id,
                $request->requested_amount,
                $request->requested_tenure,
                $request->account_id,
                $request->purpose,
                $monthlyIncome,
                $payrollGross,
                $payrollNet,
                $employmentStatus,
                $employerIdNumber,
                $request->repayment_plan
            );

            return response()->json(['success' => true, 'message' => 'Application created', 'data' => $application], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }
    }

    /**
     * POST /user/loan-applications - Create loan application for authenticated user
     */
    public function createMyApplication(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'loan_product_id' => 'required|uuid|exists:loan_products,id',
            'account_id' => 'required|uuid|exists:accounts,id',
            'requested_amount' => 'required|numeric|min:1',
            'requested_tenure' => 'required|integer|min:1',
            'repayment_plan' => 'nullable|in:monthly,weekly,quarterly,annually',
            'monthly_income' => 'nullable|numeric|min:0',
            'payroll_gross' => 'nullable|numeric|min:0',
            'payroll_net' => 'nullable|numeric|min:0',
            'employer_id_number' => 'nullable|string',
            'employment_status' => 'nullable|string',
            'purpose' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Validate account belongs to user and is LOAN type
        $account = \App\Models\Account::with('accountType')
            ->where('id', $request->account_id)
            ->where('customer_id', $user->id)
            ->first();
        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Account not found or does not belong to you'], 404);
        }
        if ($account->accountType->code !== 'LOAN') {
            return response()->json(['success' => false, 'message' => 'Only LOAN account type can be used for loan applications'], 422);
        }

        try {
            // Prefer employment data from the authenticated user's employment profile; fall back to request if missing
            $employmentProfile = $user->customerProfile?->employmentProfile;

            $employerIdNumber = $employmentProfile?->employer_id_number ?? $request->input('employer_id_number');
            $monthlyIncome = $employmentProfile?->monthly_income ?? $request->input('monthly_income');
            $payrollGross = $employmentProfile?->payroll_gross ?? $request->input('payroll_gross');
            $payrollNet = $employmentProfile?->payroll_net ?? $request->input('payroll_net');
            $employmentStatus = $employmentProfile?->employment_status ?? $request->input('employment_status');

            $application = $this->loanService->createApplication(
                $user->id,
                $request->loan_product_id,
                $request->requested_amount,
                $request->requested_tenure,
                $request->account_id,
                $request->purpose,
                $monthlyIncome,
                $payrollGross,
                $payrollNet,
                $employmentStatus,
                $employerIdNumber,
                $request->repayment_plan
            );

            return response()->json(['success' => true, 'message' => 'Application created', 'data' => $application], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * POST /loan-applications/{id}/verify - Admin/Staff verify loan application
     */
    public function verify(string $id): JsonResponse
    {
        try {
            $application = LoanApplication::findOrFail($id);

            if ($application->status !== 'submitted') {
                return response()->json(['success' => false, 'message' => 'Only submitted applications can be verified'], 422);
            }

            $application->update(['status' => 'verified']);

            return response()->json(['success' => true, 'message' => 'Application verified', 'data' => $application]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * PATCH /loan-applications/{id}/payroll - Admin/Staff update payroll amounts for a loan application
     */
    public function updateApplicationPayroll(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payroll_gross' => 'required|numeric|min:0',
            'payroll_net' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $application = LoanApplication::findOrFail($id);

        if (!auth()->user()->hasAnyRole(['admin', 'staff'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to update payroll details'], 403);
        }

        $application->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payroll amounts updated successfully',
            'data' => $application,
        ]);
    }

    /**
     * POST /loan-applications/{id}/reject - Admin/Staff reject loan application
     */
    public function rejectApplication(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $application = LoanApplication::findOrFail($id);

            if ($application->status !== 'submitted') {
                return response()->json(['success' => false, 'message' => 'Only submitted applications can be rejected'], 422);
            }

            $application->update(['status' => 'rejected', 'rejection_reason' => $request->reason]);

            return response()->json(['success' => true, 'message' => 'Application rejected', 'data' => $application]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /users/{user}/loan-applications/{id}/verify - Admin/Staff verify user's application
     */
    public function verifyUserApplication(User $user, string $id): JsonResponse
    {
        try {
            $application = LoanApplication::where('customer_id', $user->id)->findOrFail($id);

            if ($application->status !== 'submitted') {
                return response()->json(['success' => false, 'message' => 'Only submitted applications can be verified'], 422);
            }

            $application->update(['status' => 'verified']);

            return response()->json(['success' => true, 'message' => 'Application verified', 'data' => $application]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /users/{user}/loan-applications/{id}/reject - Admin/Staff reject user's application
     */
    public function rejectUserApplication(Request $request, User $user, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $application = LoanApplication::where('customer_id', $user->id)->findOrFail($id);

            if ($application->status !== 'submitted') {
                return response()->json(['success' => false, 'message' => 'Only submitted applications can be rejected'], 422);
            }

            $application->update(['status' => 'rejected', 'rejection_reason' => $request->reason]);

            return response()->json(['success' => true, 'message' => 'Application rejected', 'data' => $application]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /users/{user}/loan-applications - Admin/Staff view all applications for a user
     */
    public function userLoanApplications(User $user): JsonResponse
    {
        $applications = $user->loanApplications()->get();

        return response()->json([
            'success' => true,
            'data' => $applications,
        ]);
    }

    /**
     * GET /users/{user}/loan-applications/{application} - Admin/Staff view specific application for a user
     */
    public function userLoanApplicationDetail(User $user, LoanApplication $application): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $application,
        ]);
    }

    /**
     * POST /users/{user}/loan-applications/{application}/approve - Admin/Staff approve a submitted loan application
     */
    public function userApproveApplication(User $user, LoanApplication $application): JsonResponse
    {
        try {
            $loan = $this->loanService->approveApplication($application->id);
            return response()->json(['success' => true, 'message' => 'Loan application approved', 'data' => $loan], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /users/{user}/loan-applications/{application}/reject - Admin/Staff reject a submitted loan application
     */
    public function userRejectApplication(Request $request, User $user, LoanApplication $application): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $application = $this->loanService->rejectApplication($application->id, $request->reason);
            return response()->json(['success' => true, 'message' => 'Loan application rejected', 'data' => $application]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /users/{user}/loan-applications/{application}/disburse - Admin/Staff disburse an approved loan application
     */
    public function userDisburseApplication(Request $request, User $user, LoanApplication $application): JsonResponse
    {
        if ($application->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Only approved applications can be disbursed.'
            ], 422);
        }

        $loan = $application->loan;
        if (!$loan) {
            return response()->json([
                'success' => false,
                'message' => 'No approved loan record exists for this application.'
            ], 404);
        }

        try {
            $loan = $this->loanService->disburseLoan($loan->id, $request->account_id);
            return response()->json(['success' => true, 'message' => 'Loan disbursed', 'data' => $loan], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /loan-applications/{id}/submit
     */
    public function submitApplication(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $application = LoanApplication::with(['loanProduct', 'customer.customerProfile', 'guarantors', 'collaterals', 'documents'])
            ->where('id', $id)
            ->where('customer_id', $user->id)
            ->firstOrFail();

        $missingRequirements = $this->checkLoanRequirements($application);
        if (!empty($missingRequirements)) {
            $uploadedItems = $this->getUploadedItemsStatus($application);
            return response()->json([
                'success' => false,
                'message' => 'Application cannot be submitted due to missing requirements',
                'loan_product' => [
                    'id' => $application->loanProduct->id,
                    'name' => $application->loanProduct->name,
                    'code' => $application->loanProduct->code,
                    'requires_account' => (bool) $application->loanProduct->requires_account,
                    'requires_guarantor' => (bool) $application->loanProduct->requires_guarantor,
                    'requires_collateral' => (bool) $application->loanProduct->requires_collateral,
                    'requires_bank_statement' => (bool) $application->loanProduct->requires_bank_statement,
                    'requires_proof_income' => (bool) $application->loanProduct->requires_proof_income,
                    'requires_passport' => (bool) $application->loanProduct->requires_passport,
                    'min_guarantors' => $application->loanProduct->min_guarantors,
                ],
                'uploaded_items' => $uploadedItems,
                'missing_requirements' => $missingRequirements,
            ], 422);
        }

        try {
            $application->submit();
            return response()->json(['success' => true, 'message' => 'Application submitted', 'data' => $application->fresh(['loanProduct', 'customer.customerProfile'])]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /user/loan-applications/{id}/submit - Submit user's loan application with requirement checks
     */
    public function submitMyApplication(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $application = LoanApplication::with(['loanProduct', 'customer.customerProfile', 'guarantors', 'collaterals', 'documents'])
            ->where('id', $id)
            ->where('customer_id', $user->id)
            ->firstOrFail();

        // Validate and merge profile fields if provided
        $profileData = $request->validate([
            'address' => 'nullable|string|max:500',
            'dob' => 'nullable|date',
            'bvn' => 'nullable|string|max:11',
            'nin' => 'nullable|string|max:11',
            'employment_status' => 'nullable|string|max:100',
            'monthly_income' => 'nullable|numeric|min:0',
        ]);

        // Update customer profile with provided fields
        if (array_filter($profileData)) {
            $profile = $application->customer->customerProfile;
            
            // Handle BVN encryption if provided
            if (!empty($profileData['bvn'])) {
                $bvnClean = preg_replace('/\s+/', '', strtoupper($profileData['bvn']));
                $profileData['bvn_encrypted'] = \Illuminate\Support\Facades\Crypt::encryptString($bvnClean);
                $profileData['bvn_hash'] = hash('sha256', $bvnClean);
                unset($profileData['bvn']);
            }

            $profile->update(array_filter($profileData));
            
            // Refresh the application with updated profile
            $application->load(['loanProduct', 'customer.customerProfile', 'guarantors', 'collaterals', 'documents']);
        }

        // Reload application with all required relationships to ensure fresh data
        $application->load(['loanProduct', 'customer.customerProfile', 'guarantors', 'collaterals', 'documents']);

        // Check loan product requirements
        // Ensure employer id number is present on application or profile; copy from the employment profile if available
        $employmentProfile = $application->customer->customerProfile?->employmentProfile;
        $profileEmp = $employmentProfile?->employer_id_number ?? null;
        if (empty($application->employer_id_number) && ! empty($profileEmp)) {
            $application->update(['employer_id_number' => $profileEmp]);
            $application->refresh();
        }

        $missingRequirements = $this->checkLoanRequirements($application);

        if (!empty($missingRequirements)) {
            // Create notifications for missing requirements (admin, staff, referrer)
            $this->notifyMissingRequirements($application, $missingRequirements);

            // Get uploaded items status
            $uploadedItems = $this->getUploadedItemsStatus($application);

            return response()->json([
                'success' => false,
                'message' => 'Application cannot be submitted due to missing requirements',
                'loan_product' => [
                    'id' => $application->loanProduct->id,
                    'name' => $application->loanProduct->name,
                    'code' => $application->loanProduct->code,
                    'requires_account' => (bool) $application->loanProduct->requires_account,
                    'requires_guarantor' => (bool) $application->loanProduct->requires_guarantor,
                    'requires_collateral' => (bool) $application->loanProduct->requires_collateral,
                    'requires_bank_statement' => (bool) $application->loanProduct->requires_bank_statement,
                    'requires_proof_income' => (bool) $application->loanProduct->requires_proof_income,
                    'requires_passport' => (bool) $application->loanProduct->requires_passport,
                    'min_guarantors' => $application->loanProduct->min_guarantors,
                ],
                'uploaded_items' => $uploadedItems,
                'missing_requirements' => $missingRequirements
            ], 422);
        }

        try {
            $application->submit();
            return response()->json([
                'success' => true,
                'message' => 'Application submitted successfully',
                'data' => $application->fresh(['loanProduct', 'customer.customerProfile'])
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Check loan product requirements for an application
     */
    private function checkLoanRequirements(LoanApplication $application): array
    {
        $missing = [];
        $product = $application->loanProduct;
        $profile = $application->customer->customerProfile;
        $employmentProfile = $profile?->employmentProfile;

        $profileTier = $profile->kyc_tier ?? 0;

        // Always required: KYC verification
        if ($profile->kyc_status !== 'verified') {
            $missing[] = 'KYC verification required';
        }

        // Persistent customer KYC tier requirements
        if ($profileTier < 1) {
            $missing[] = 'KYC tier 1 is required for all loan applications. Upload and verify at least one persistent KYC document.';
        }

        if ($this->isGovtLoanProduct($product) && $profileTier < 2) {
            $missing[] = 'KYC tier 2 is required for government loan products. Upload and verify a government-issued ID combination via persistent customer KYC documents.';
        }

        // Always required: BVN
        if (empty($profile->bvn_hash)) {
            $missing[] = 'BVN required';
        }

        // Always required: NIN
        if (empty($profile->nin)) {
            $missing[] = 'NIN required';
        }

        // Product-specific: Account requirement
        if ($product->requires_account && empty($application->account_id)) {
            $missing[] = 'Loan repayment account required (required by this product)';
        }

        // Product-specific: Guarantors requirement
        if ($product->requires_guarantor) {
            $underReviewGuarantors = $application->guarantors()->where('status', 'under_review')->count();
            $verifiedGuarantors = $application->guarantors()->where('status', 'verified')->count();
            $totalValidGuarantors = $underReviewGuarantors + $verifiedGuarantors;
            $minGuarantors = $product->min_guarantors ?? 1;

            if ($totalValidGuarantors < $minGuarantors) {
                // Check if any guarantors are under review
                if ($underReviewGuarantors > 0) {
                    $missing[] = "Guarantor upload under review (need {$minGuarantors}, currently {$totalValidGuarantors} valid)";
                } else {
                    $missing[] = "At least {$minGuarantors} guarantor(s) required by this product";
                }
            } else {
                // Check that each non-rejected guarantor has both notes uploaded
                $guarantors = $application->guarantors()->whereNot('status', 'rejected')->get();
                foreach ($guarantors as $guarantor) {
                    if (empty($guarantor->note_1_url)) {
                        $missing[] = "Guarantor {$guarantor->name}: Note 1 document required";
                    }
                    if (empty($guarantor->note_2_url)) {
                        $missing[] = "Guarantor {$guarantor->name}: Note 2 document required";
                    }
                }
            }
        }

        // Product-specific: Collateral requirement
        if ($product->requires_collateral) {
            $underReviewCollaterals = $application->collaterals()->where('status', 'under_review')->count();
            $verifiedCollaterals = $application->collaterals()->where('status', 'verified')->count();
            $totalValidCollaterals = $underReviewCollaterals + $verifiedCollaterals;

            if ($totalValidCollaterals === 0) {
                $missing[] = 'Collateral required by this product';
            }
        }

        // Product-specific: Tenure requirements
        if ($application->requested_tenure < $product->tenure_min_months ||
            $application->requested_tenure > $product->tenure_max_months) {
            $missing[] = "Tenure must be between {$product->tenure_min_months} and {$product->tenure_max_months} months (product requirement)";
        }

        // Product-specific: Amount requirements
        if ($application->requested_amount < $product->min_amount ||
            $application->requested_amount > $product->max_amount) {
            $missing[] = "Amount must be between {$product->min_amount} and {$product->max_amount} (product requirement)";
        }

        // Employer ID number must be present on submission (can be stored on the employment profile)
        if (empty($application->employer_id_number) && empty($employmentProfile?->employer_id_number)) {
            $missing[] = 'Employer ID number is required before submitting this application.';
        }

        if ($this->requiresEmploymentProfile($product)) {
            if (! $employmentProfile || $employmentProfile->employment_profile_status !== 'verified') {
                $missing[] = 'Verified employment profile is required for this loan product.';
            }

            if ($this->isGovtLoanProduct($product) && strtolower($employmentProfile?->employer_type ?? '') !== 'government') {
                $missing[] = 'Government loan products require an employer_type of government.';
            }

            if ($this->isSalaryLoanProduct($product) && strtolower($employmentProfile?->employer_type ?? '') !== 'private') {
                $missing[] = 'Salary loan products require an employer_type of private.';
            }

            if ($product->required_employer_type && strtolower($employmentProfile?->employer_type ?? '') !== strtolower($product->required_employer_type)) {
                $missing[] = 'Employer type must be ' . $product->required_employer_type . ' for this loan product.';
            }

            if (empty($employmentProfile?->payroll_gross)) {
                $missing[] = 'Gross payroll amount is required for this loan product.';
            }
            if (empty($employmentProfile?->payroll_net)) {
                $missing[] = 'Net payroll amount is required for this loan product.';
            }
        }

        // Product-specific: Bank statement requirement
        if ($product->requires_bank_statement) {
            $underReviewBankStatement = $application->documents()
                ->where('document_type', 'bank_statement')
                ->where('status', 'under_review')
                ->exists();
            $verifiedBankStatement = $application->documents()
                ->where('document_type', 'bank_statement')
                ->where('status', 'verified')
                ->exists();

            if (!$underReviewBankStatement && !$verifiedBankStatement) {
                $missing[] = 'Bank statement required by this product (upload via loan application documents)';
            } elseif ($underReviewBankStatement) {
                $missing[] = 'Bank statement upload under review';
            }
        }

        // Product-specific: Passport photograph requirement should be satisfied by global customer KYC.
        if ($product->requires_passport) {
            $profileDocuments = $application->customer->customerProfile?->kycDocuments()
                ->whereIn('document_type', [
                    KycDocument::TYPE_PASSPORT_PHOTO,
                    KycDocument::TYPE_PASSPORT_DOCUMENT,
                ])
                ->get() ?? collect();

            $approvedPassport = $profileDocuments->filter(fn (KycDocument $doc) => $doc->verification_status === KycDocument::VERIFICATION_APPROVED);
            $pendingPassport = $profileDocuments->filter(fn (KycDocument $doc) => $doc->verification_status === KycDocument::VERIFICATION_PENDING);
            $rejectedPassport = $profileDocuments->filter(fn (KycDocument $doc) => $doc->verification_status === KycDocument::VERIFICATION_REJECTED);

            $hasPassportPhotoApproved = $approvedPassport->where('document_type', KycDocument::TYPE_PASSPORT_PHOTO)->isNotEmpty();
            $hasPassportDocumentApproved = $approvedPassport->where('document_type', KycDocument::TYPE_PASSPORT_DOCUMENT)->isNotEmpty();

            $loanPassportDocuments = $application->documents()
                ->whereIn('document_type', ['passport_photograph', 'selfie', 'passport_photo'])
                ->get();
            $verifiedLoanPassport = $loanPassportDocuments->where('status', 'verified');
            $pendingLoanPassport = $loanPassportDocuments->where('status', 'under_review');
            $rejectedLoanPassport = $loanPassportDocuments->where('status', 'rejected');

            $passportSatisfiedByKyc = $hasPassportPhotoApproved && $hasPassportDocumentApproved;
            $passportSatisfiedByLoan = $verifiedLoanPassport->isNotEmpty();

            if ($passportSatisfiedByKyc || $passportSatisfiedByLoan) {
                // requirement satisfied by either persistent KYC passport documents or loan-specific passport document
            } elseif ($pendingPassport->isNotEmpty() || $pendingLoanPassport->isNotEmpty()) {
                $missing[] = 'Passport photograph upload under review';
            } elseif ($rejectedPassport->isNotEmpty() || $rejectedLoanPassport->isNotEmpty()) {
                $missing[] = 'Passport photograph was rejected; please re-upload it using the correct endpoint.';
            } else {
                $missing[] = 'Passport photograph required by this product (upload via customer KYC documents or loan application documents).';
            }
        }

        // Product-specific: Proof of income requirement
        if ($product->requires_proof_income) {
            $underReviewProofIncome = $application->documents()
                ->where('document_type', 'proof_income')
                ->where('status', 'under_review')
                ->exists();
            $verifiedProofIncome = $application->documents()
                ->where('document_type', 'proof_income')
                ->where('status', 'verified')
                ->exists();

            if (!$underReviewProofIncome && !$verifiedProofIncome) {
                $missing[] = 'Proof of income required by this product (upload via loan application documents)';
            } elseif ($underReviewProofIncome) {
                $missing[] = 'Proof of income upload under review';
            }
        }

        // Product-specific: Cooperative savings account requirement
        if (!empty($product->required_cooperative_account_type_ids) && is_array($product->required_cooperative_account_type_ids)) {
            $requiredIds = $product->required_cooperative_account_type_ids;
            $hasCoopAccount = Account::where('customer_id', $application->customer_id)
                ->whereIn('account_type_id', $requiredIds)
                ->where('status', 'active')
                ->exists();

            if (! $hasCoopAccount) {
                $missing[] = 'Active cooperative savings account required by this product (one of the product-specific cooperative account types)';
            }
        }

        return $missing;
    }

    private function isGovtLoanProduct(LoanProduct $product): bool
    {
        return in_array($product->code, [
            'FEDERAL_GOVT',
            'STATE_GOVT',
            'LOCAL_GOVT',
            'FEDERAL_GOVT_LOAN',
            'STATE_GOVT_LOAN',
            'LOCAL_GOVT_LOAN',
        ], true) || strtolower($product->required_employer_type ?? '') === 'government';
    }

    private function isSalaryLoanProduct(LoanProduct $product): bool
    {
        return in_array($product->code, ['SALARY_LOAN', 'SAL'], true)
            || strtolower($product->required_employer_type ?? '') === 'private';
    }

    private function requiresEmploymentProfile(LoanProduct $product): bool
    {
        return $product->requires_employment_profile || $this->isGovtLoanProduct($product) || $this->isSalaryLoanProduct($product);
    }

    private function hasApprovedGovtIdCard(array $approvedTypes): bool
    {
        $hasIdCard = in_array(KycDocument::TYPE_ID_CARD_FRONT, $approvedTypes, true)
            && in_array(KycDocument::TYPE_ID_CARD_BACK, $approvedTypes, true);
        $hasPassport = in_array(KycDocument::TYPE_PASSPORT_PHOTO, $approvedTypes, true)
            && in_array(KycDocument::TYPE_PASSPORT_DOCUMENT, $approvedTypes, true);
        $hasDriverLicense = in_array(KycDocument::TYPE_DRIVERS_LICENSE, $approvedTypes, true);

        return $hasIdCard || $hasPassport || $hasDriverLicense;
    }

    /**
     * Get status of all uploaded items (documents, collaterals, guarantors, KYC)
     */
    private function getUploadedItemsStatus(LoanApplication $application): array
    {
        $uploadedItems = [
            'kyc' => null,
            'documents' => [],
            'collaterals' => [],
            'guarantors' => [],
        ];

        // KYC status
        $kyc = $application->customer->customerProfile;
        if ($kyc) {
            $uploadedItems['kyc'] = [
                'status' => $kyc->kyc_status,
                'tier' => $kyc->kyc_tier,
                'verified_at' => $kyc->kyc_verified_at,
                'rejection_reason' => $kyc->rejection_reason ?? null,
            ];
        }

        // Documents
        $documents = $application->documents()->get();
        foreach ($documents as $doc) {
            $uploadedItems['documents'][] = [
                'id' => $doc->id,
                'type' => $doc->document_type,
                'filename' => $doc->filename,
                'status' => $doc->status,
                'uploaded_at' => $doc->created_at,
                'verified_at' => $doc->verified_at,
                'rejection_reason' => $doc->rejection_reason ?? null,
                'verification_notes' => $doc->verification_notes ?? null,
            ];
        }

        // Collaterals
        $collaterals = $application->collaterals()->get();
        foreach ($collaterals as $collateral) {
            $uploadedItems['collaterals'][] = [
                'id' => $collateral->id,
                'type' => $collateral->type,
                'description' => $collateral->description,
                'estimated_value' => $collateral->estimated_value,
                'status' => $collateral->status,
                'uploaded_at' => $collateral->created_at,
                'rejection_reason' => $collateral->rejection_reason ?? null,
                'verification_notes' => $collateral->verification_notes ?? null,
            ];
        }

        // Guarantors
        $guarantors = $application->guarantors()->get();
        foreach ($guarantors as $guarantor) {
            $uploadedItems['guarantors'][] = [
                'id' => $guarantor->id,
                'name' => $guarantor->name,
                'relationship' => $guarantor->relationship,
                'status' => $guarantor->status,
                'note_1_url' => $guarantor->note_1_url,
                'note_2_url' => $guarantor->note_2_url,
                'created_at' => $guarantor->created_at,
                'rejection_reason' => $guarantor->rejection_reason ?? null,
            ];
        }

        return $uploadedItems;
    }

    /**
     * Notify admin, staff, and referrer about missing requirements
     */
    private function notifyMissingRequirements(LoanApplication $application, array $missingRequirements): void
    {
        $message = "Loan application {$application->application_no} has missing requirements: " . implode(', ', $missingRequirements);

        // TODO: Implement notifications for admin, staff, and referrer
        // This could use Laravel notifications, database notifications, or email
        // For now, we'll just log it
        \Illuminate\Support\Facades\Log::info('Missing loan requirements', [
            'application_id' => $application->id,
            'application_no' => $application->application_no,
            'customer_id' => $application->customer_id,
            'missing_requirements' => $missingRequirements,
        ]);
    }

    /**
     * POST /loan-applications/{id}/cancel
     */
    public function cancelApplication(string $id): JsonResponse
    {
        try {
            $application = $this->loanService->cancelApplication($id);
            return response()->json(['success' => true, 'message' => 'Application cancelled', 'data' => $application]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ================== LOANS ==================

    /**
     * GET /loans
     */
    public function index(Request $request): JsonResponse
    {
        $query = Loan::with(['application.loanProduct', 'customer'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->customer_id, fn($q, $c) => $q->where('customer_id', $c))
            ->when($request->product_id, fn($q, $p) => $q->whereHas('application', fn($a) => $a->where('loan_product_id', $p)))
            ->when($request->start_date, fn($q, $d) => $q->where('disbursed_at', '>=', $d))
            ->when($request->end_date, fn($q, $d) => $q->where('disbursed_at', '<=', $d));

        $loans = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 20);
        return response()->json(['success' => true, 'data' => $loans]);
    }

    /**
     * GET /loans/{id}
     */
    public function show(string $id): JsonResponse
    {
        $loan = Loan::with(['application.loanProduct', 'customer', 'account', 'schedules', 'repayments'])
            ->findOrFail($id);
        return response()->json(['success' => true, 'data' => $loan]);
    }

    /**
     * POST /loans/{id}/approve
     */
    public function approve(string $id): JsonResponse
    {
        try {
            $loan = $this->loanService->approveApplication($id);
            return response()->json(['success' => true, 'message' => 'Loan application approved successfully', 'data' => $loan], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * POST /loans/{id}/reject
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $application = $this->loanService->rejectApplication($id, $request->reason);
            return response()->json(['success' => true, 'message' => 'Application rejected', 'data' => $application]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * POST /loans/{id}/disburse
     */
    public function disburse(Request $request, string $id): JsonResponse
    {
        try {
            $loan = $this->loanService->disburseLoan($id, $request->account_id);
            return response()->json(['success' => true, 'message' => 'Loan disbursed', 'data' => $loan], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * POST /loans/{id}/repay
     */
    public function repay(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'account_id' => 'nullable|uuid|exists:accounts,id',
            'payment_channel' => 'nullable|string',
            'reference' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $accountId = $request->account_id;

            if (Auth::user()->hasRole('customer') && $accountId) {
                $account = Account::where('id', $accountId)
                    ->where('customer_id', Auth::id())
                    ->first();

                if (! $account) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Account not found or does not belong to you',
                    ], 404);
                }
            }

            $repayment = $this->loanService->recordRepayment(
                $id,
                $request->amount,
                $accountId,
                $request->payment_channel,
                $request->reference
            );

            $loan = $this->loanService->allocateRepayment($repayment->id);
            
            // Post ledger entries for the repayment
            $this->loanService->postRepaymentLedgerEntries($repayment);

            return response()->json([
                'success' => true,
                'message' => 'Repayment recorded',
                'data' => $repayment->load('loan'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * POST /loans/{id}/payoff
     */
    public function payoff(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'nullable|uuid|exists:accounts,id',
            'payment_channel' => 'nullable|string',
            'reference' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if (Auth::user()->hasRole('customer') && $request->account_id) {
            $account = Account::where('id', $request->account_id)
                ->where('customer_id', Auth::id())
                ->first();

            if (! $account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found or does not belong to you',
                ], 404);
            }
        }

        try {
            $loan = $this->loanService->payoffLoan(
                $id,
                $request->account_id,
                $request->payment_channel,
                $request->reference
            );

            return response()->json([
                'success' => true,
                'message' => 'Loan payoff processed',
                'data' => $loan,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * POST /loans/{id}/manual-repayment
     */
    public function manualRepayment(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'account_id' => 'nullable|uuid|exists:accounts,id',
            'payment_channel' => 'nullable|string',
            'reference' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $loan = Loan::findOrFail($id);
            $accountId = $request->account_id ?: $loan->account_id;

            $repayment = $this->loanService->recordRepayment(
                $id,
                $request->amount,
                $accountId,
                $request->payment_channel,
                $request->reference,
                true
            );

            $loan = $this->loanService->allocateRepayment($repayment->id);

            return response()->json([
                'success' => true,
                'message' => 'Manual repayment recorded',
                'data' => $loan,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * GET /loans/{id}/schedule
     */
    public function schedule(string $id): JsonResponse
    {
        $schedule = $this->loanService->getSchedule($id);
        return response()->json(['success' => true, 'data' => $schedule]);
    }

    /**
     * POST /loans/{id}/restructure
     */
    public function restructure(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'new_tenure' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $loan = $this->loanService->restructureLoan($id, $request->new_tenure);
            return response()->json(['success' => true, 'message' => 'Loan restructured', 'data' => $loan], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * POST /loans/{id}/writeoff
     */
    public function writeoff(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $loan = $this->loanService->writeOffLoan($id, $request->reason);
        return response()->json(['success' => true, 'message' => 'Loan written off', 'data' => $loan]);
    }

    // ================== USER LOANS ==================

    /**
     * GET /users/{user_id}/loan-applications/
     */
    public function userApplications(Request $request, string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        Gate::authorize('view', $user);

        $applications = LoanApplication::with(['loanProduct'])
            ->where('customer_id', $userId)
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $applications]);
    }

    /**     * GET /user/loan-applications - Get authenticated user's loan applications
     */
    public function myApplications(Request $request): JsonResponse
    {
        $user = Auth::user();

        $applications = LoanApplication::with(['loanProduct'])
            ->where('customer_id', $user->id)
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $applications]);
    }

    /**
     * GET /user/loan-applications/{id} - Get authenticated user's loan application detail
     */
    public function myApplicationDetail(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        $application = LoanApplication::with(['loanProduct', 'guarantors', 'collaterals', 'documents'])
            ->where('customer_id', $user->id)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $application]);
    }

    /**     * GET /users/{user_id}/loans-applications/{id}
     */
    public function userApplicationDetail(Request $request, string $userId, string $id): JsonResponse
    {
        $user = User::findOrFail($userId);
        Gate::authorize('view', $user);

        $application = LoanApplication::with(['loanProduct', 'guarantors', 'collaterals', 'documents'])
            ->where('customer_id', $userId)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $application]);
    }

    /**
     * GET /user/loans
     */
    public function userLoans(Request $request): JsonResponse
    {
        $user = Auth::user();

        $loans = Loan::with(['application.loanProduct'])
            ->where('customer_id', $user->id)
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $loans]);
    }

    /**
     * GET /user/loans/{id}
     */
    public function userLoanDetail(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        $loan = Loan::with(['application.loanProduct', 'schedules', 'repayments'])
            ->where('customer_id', $user->id)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $loan]);
    }

    /**
     * POST /user/loan-applications/{application}/collateral
     * Upload collateral document (PDF only)
     */
    public function uploadCollateral(Request $request, LoanApplication $application): JsonResponse
    {
        // Check if application is still draft
        if ($application->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot upload collateral for submitted applications'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:land_title,deed_of_assignment,vehicle_registration,other',
            'description' => 'required|string|max:255',
            'estimated_value' => 'required|numeric|min:0',
            'file' => 'required|file|mimes:pdf|max:5120', // 5MB PDF only
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $path = $file->store('collateral/' . $application->id, 'public');

        $collateral = LoanCollateral::create([
            'loan_application_id' => $application->id,
            'type' => $request->type,
            'description' => $request->description,
            'estimated_value' => $request->estimated_value,
            'document_url' => Storage::url($path),
            'document_type' => 'pdf',
            'status' => 'under_review',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Collateral document uploaded successfully',
            'data' => $collateral,
        ], 201);
    }

    /**
     * POST /user/loan-applications/{application}/guarantors
     * Add a guarantor to a loan application
     */
    public function addGuarantor(Request $request, LoanApplication $application): JsonResponse
    {
        // Check if application is still draft
        if ($application->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add guarantors to submitted applications'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'relationship' => 'required|string|max:100',
            'employer' => 'nullable|string|max:255',
            'employer_phone' => 'nullable|string|max:20',
            'monthly_income' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $guarantor = LoanGuarantor::create([
            'loan_application_id' => $application->id,
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
            'relationship' => $request->relationship,
            'employer' => $request->employer,
            'employer_phone' => $request->employer_phone,
            'monthly_income' => $request->monthly_income,
            'status' => 'under_review',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Guarantor added successfully',
            'data' => $guarantor,
        ], 201);
    }

    /**
     * POST /user/loan-applications/{application}/guarantors/{guarantor}/notes
     * Upload guarantor notes (PDF or photos)
     */
    public function uploadGuarantorNotes(Request $request, LoanApplication $application, LoanGuarantor $guarantor): JsonResponse
    {
        // Ensure guarantor belongs to the application
        if ($guarantor->loan_application_id !== $application->id) {
            return response()->json([
                'success' => false,
                'message' => 'Guarantor not found for this application'
            ], 404);
        }

        // Check if application is still draft
        if ($application->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot upload guarantor notes for submitted applications'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'note_type' => 'required|string|in:note_1,note_2',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB PDF or images
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $noteType = $request->note_type;
        $path = $file->store('guarantor_notes/' . $application->id . '/' . $guarantor->id, 'public');

        // Update the guarantor with the note URL
        $field = $noteType . '_url'; // note_1_url or note_2_url
        $guarantor->update([
            $field => Storage::url($path),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Guarantor note uploaded successfully',
            'data' => [
                'guarantor' => $guarantor->fresh(),
                'note_type' => $noteType,
                'url' => Storage::url($path),
            ],
        ], 201);
    }

    /**
     * POST /user/loan-applications/{application}/documents
     * Upload loan application specific documents (passport photo, guarantor form, etc.)
     */
    public function uploadDocument(Request $request, LoanApplication $application): JsonResponse
    {
        // Check if application is still draft
        if ($application->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot upload documents for submitted applications'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'document_type' => 'required|string|in:guarantor_form,bank_statement,proof_income,passport_photograph,selfie,passport_photo',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $documentType = $request->document_type;

        // Check if this document type already exists for this application
        $existing = $application->documents()->where('document_type', $documentType)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => "Document type '{$documentType}' has already been uploaded for this application.",
            ], 422);
        }

        $file = $request->file('file');
        $path = $file->store('loan_documents/' . $application->id, 'public');

        $document = LoanApplicationDocument::create([
            'loan_application_id' => $application->id,
            'document_type' => $documentType,
            'file_url' => Storage::url($path),
            'filename' => $file->getClientOriginalName(),
            'status' => 'under_review',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'data' => $document,
        ], 201);
    }

    /**
     * GET /user/loan-applications/{application}/documents
     * Customer can view their own loan application documents.
     */
    public function myApplicationDocuments(Request $request, LoanApplication $application): JsonResponse
    {
        $documents = $application->documents()->get();

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    /**
     * GET /user/loan-applications/{application}/collaterals
     * Customer can view their own loan application collaterals.
     */
    public function myApplicationCollaterals(Request $request, LoanApplication $application): JsonResponse
    {
        $collaterals = $application->collaterals()->get();

        return response()->json([
            'success' => true,
            'data' => $collaterals,
        ]);
    }

    /**
     * GET /user/loan-applications/{application}/guarantors
     * Customer can view their own loan application guarantors.
     */
    public function myApplicationGuarantors(Request $request, LoanApplication $application): JsonResponse
    {
        $guarantors = $application->guarantors()->get();

        return response()->json([
            'success' => true,
            'data' => $guarantors,
        ]);
    }

    /**
     * GET /users/{user}/loan-applications/{application}/documents
     * Admin/staff can view documents for a customer loan application.
     */
    public function userApplicationDocuments(Request $request, User $user, LoanApplication $application): JsonResponse
    {
        $documents = $application->documents()->get();
        $collaterals = $application->collaterals()->get();
        $guarantors = $application->guarantors()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'documents' => $documents,
                'collaterals' => $collaterals,
                'guarantors' => $guarantors,
            ],
        ]);
    }

    /**
     * PATCH /users/{user}/loan-applications/{application}/documents
     * Update the status of a loan application document.
     */
    public function updateApplicationDocumentStatus(Request $request, User $user, LoanApplication $application): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_id' => 'nullable|uuid|exists:loan_application_documents,id',
            'document_type' => 'nullable|string|in:guarantor_form,bank_statement,proof_income,passport_photograph,selfie,passport_photo',
            'status' => 'required|in:pending,verified,rejected',
            'rejection_reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if (!$request->filled('document_id') && !$request->filled('document_type')) {
            return response()->json([
                'success' => false,
                'message' => 'Either document_id or document_type is required to update document status.'
            ], 422);
        }

        $documentQuery = $application->documents();

        if ($request->filled('document_id')) {
            $documentQuery->where('id', $request->document_id);
        } else {
            $documentQuery->where('document_type', $request->document_type);
        }

        $document = $documentQuery->first();

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found for this application.'
            ], 404);
        }

        $document->update([
            'status' => $request->status,
            'verified_at' => $request->status === 'verified' ? now() : null,
            'verified_by' => $request->status === 'verified' ? $request->user()->id : null,
        ]);

        if ($request->status === 'rejected' && $request->filled('rejection_reason')) {
            $document->rejection_reason = $request->rejection_reason;
            $document->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Document status updated successfully',
            'data' => $document,
        ]);
    }

    /**
     * POST /users/{user}/loan-applications/{application}/documents/verify
     * Admin/staff verify a loan application document.
     */
    public function verifyApplicationDocument(Request $request, User $user, LoanApplication $application): JsonResponse
    {
        if (!in_array($application->status, ['draft', 'under_review'])) {
            return response()->json([
                'success' => false,
                'message' => 'Document verification is only allowed for applications in draft or under_review status.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'document_ids' => 'nullable|array',
            'document_ids.*' => 'uuid|exists:loan_application_documents,id',
            'document_id' => 'nullable|uuid|exists:loan_application_documents,id',
            'document_type' => 'nullable|string|in:guarantor_form,bank_statement,proof_income,passport_photograph,selfie,passport_photo',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Check if bulk operation
        if ($request->filled('document_ids')) {
            $documentQuery = $application->documents()->whereIn('id', $request->document_ids);
        } elseif ($request->filled('document_id')) {
            $documentQuery = $application->documents()->where('id', $request->document_id);
        } elseif ($request->filled('document_type')) {
            $documentQuery = $application->documents()->where('document_type', $request->document_type);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either document_ids (for bulk), document_id, or document_type is required to verify documents.'
            ], 422);
        }

        $documents = $documentQuery->get();
        if ($documents->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No documents found matching the criteria.'
            ], 404);
        }

        $updatedDocuments = [];
        foreach ($documents as $document) {
            $document->update([
                'status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $request->user()->id,
                'rejection_reason' => null,
                'verification_notes' => $request->notes,
            ]);
            $updatedDocuments[] = $document;
        }

        return response()->json([
            'success' => true,
            'message' => count($updatedDocuments) . ' document(s) verified successfully',
            'data' => $updatedDocuments,
        ]);
    }

    /**
     * POST /users/{user}/loan-applications/{application}/documents/reject
     * Admin/staff reject a loan application document.
     */
    public function rejectApplicationDocument(Request $request, User $user, LoanApplication $application): JsonResponse
    {
        if (!in_array($application->status, ['draft', 'under_review'])) {
            return response()->json([
                'success' => false,
                'message' => 'Document rejection is only allowed for applications in draft or under_review status.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'document_ids' => 'nullable|array',
            'document_ids.*' => 'uuid|exists:loan_application_documents,id',
            'document_id' => 'nullable|uuid|exists:loan_application_documents,id',
            'document_type' => 'nullable|string|in:guarantor_form,bank_statement,proof_income,passport_photograph,selfie,passport_photo',
            'rejection_reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Check if bulk operation
        if ($request->filled('document_ids')) {
            $documentQuery = $application->documents()->whereIn('id', $request->document_ids);
        } elseif ($request->filled('document_id')) {
            $documentQuery = $application->documents()->where('id', $request->document_id);
        } elseif ($request->filled('document_type')) {
            $documentQuery = $application->documents()->where('document_type', $request->document_type);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either document_ids (for bulk), document_id, or document_type is required to reject documents.'
            ], 422);
        }

        $documents = $documentQuery->get();
        if ($documents->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No documents found matching the criteria.'
            ], 404);
        }

        $updatedDocuments = [];
        foreach ($documents as $document) {
            $document->update([
                'status' => 'rejected',
                'verified_at' => null,
                'verified_by' => null,
                'rejection_reason' => $request->rejection_reason,
                'verification_notes' => $request->notes,
            ]);
            $updatedDocuments[] = $document;
        }

        return response()->json([
            'success' => true,
            'message' => count($updatedDocuments) . ' document(s) rejected successfully',
            'data' => $updatedDocuments,
        ]);
    }

    /**
     * POST /users/{user}/loan-applications/{application}/documents/under-review
     * Admin/staff put application documents under review.
     */
    public function underReviewApplicationDocument(Request $request, User $user, LoanApplication $application): JsonResponse
    {
        if (!in_array($application->status, ['draft', 'under_review'])) {
            return response()->json([
                'success' => false,
                'message' => 'Document under-review is only allowed for applications in draft or under_review status.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'document_ids' => 'nullable|array',
            'document_ids.*' => 'uuid|exists:loan_application_documents,id',
            'document_id' => 'nullable|uuid|exists:loan_application_documents,id',
            'document_type' => 'nullable|string|in:guarantor_form,bank_statement,proof_income,passport_photograph,selfie,passport_photo',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Check if bulk operation
        if ($request->filled('document_ids')) {
            $documentQuery = $application->documents()->whereIn('id', $request->document_ids);
        } elseif ($request->filled('document_id')) {
            $documentQuery = $application->documents()->where('id', $request->document_id);
        } elseif ($request->filled('document_type')) {
            $documentQuery = $application->documents()->where('document_type', $request->document_type);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either document_ids (for bulk), document_id, or document_type is required to put documents under review.'
            ], 422);
        }

        $documents = $documentQuery->get();
        if ($documents->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No documents found matching the criteria.'
            ], 404);
        }

        $updatedDocuments = [];
        foreach ($documents as $document) {
            $document->update([
                'status' => 'under_review',
                'verified_at' => null,
                'verified_by' => null,
                'rejection_reason' => null,
                'verification_notes' => $request->notes,
            ]);
            $updatedDocuments[] = $document;
        }

        return response()->json([
            'success' => true,
            'message' => count($updatedDocuments) . ' document(s) put under review successfully',
            'data' => $updatedDocuments,
        ]);
    }

    /**
     * POST /users/{user}/loan-applications/{application}/collaterals/verify
     * Admin/staff verify application collaterals.
     */
    public function verifyApplicationCollateral(Request $request, User $user, LoanApplication $application): JsonResponse
    {
        if (!in_array($application->status, ['draft', 'under_review'])) {
            return response()->json([
                'success' => false,
                'message' => 'Collateral verification is only allowed for applications in draft or under_review status.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'collateral_ids' => 'nullable|array',
            'collateral_ids.*' => 'uuid|exists:loan_collaterals,id',
            'collateral_id' => 'nullable|uuid|exists:loan_collaterals,id',
            'collateral_type' => 'nullable|string',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Check if bulk operation
        if ($request->filled('collateral_ids')) {
            $collateralQuery = $application->collaterals()->whereIn('id', $request->collateral_ids);
        } elseif ($request->filled('collateral_id')) {
            $collateralQuery = $application->collaterals()->where('id', $request->collateral_id);
        } elseif ($request->filled('collateral_type')) {
            $collateralQuery = $application->collaterals()->where('type', $request->collateral_type);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either collateral_ids (for bulk), collateral_id, or collateral_type is required to verify collaterals.'
            ], 422);
        }

        $collaterals = $collateralQuery->get();
        if ($collaterals->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No collaterals found matching the criteria.'
            ], 404);
        }

        $updatedCollaterals = [];
        foreach ($collaterals as $collateral) {
            $collateral->update([
                'status' => 'verified',
                'verification_notes' => $request->notes,
            ]);
            $updatedCollaterals[] = $collateral;
        }

        return response()->json([
            'success' => true,
            'message' => count($updatedCollaterals) . ' collateral(s) verified successfully',
            'data' => $updatedCollaterals,
        ]);
    }

    /**
     * POST /users/{user}/loan-applications/{application}/collaterals/reject
     * Admin/staff reject application collaterals.
     */
    public function rejectApplicationCollateral(Request $request, User $user, LoanApplication $application): JsonResponse
    {
        if (!in_array($application->status, ['draft', 'under_review'])) {
            return response()->json([
                'success' => false,
                'message' => 'Collateral rejection is only allowed for applications in draft or under_review status.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'collateral_ids' => 'nullable|array',
            'collateral_ids.*' => 'uuid|exists:loan_collaterals,id',
            'collateral_id' => 'nullable|uuid|exists:loan_collaterals,id',
            'collateral_type' => 'nullable|string',
            'rejection_reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Check if bulk operation
        if ($request->filled('collateral_ids')) {
            $collateralQuery = $application->collaterals()->whereIn('id', $request->collateral_ids);
        } elseif ($request->filled('collateral_id')) {
            $collateralQuery = $application->collaterals()->where('id', $request->collateral_id);
        } elseif ($request->filled('collateral_type')) {
            $collateralQuery = $application->collaterals()->where('type', $request->collateral_type);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either collateral_ids (for bulk), collateral_id, or collateral_type is required to reject collaterals.'
            ], 422);
        }

        $collaterals = $collateralQuery->get();
        if ($collaterals->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No collaterals found matching the criteria.'
            ], 404);
        }

        $updatedCollaterals = [];
        foreach ($collaterals as $collateral) {
            $collateral->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'verification_notes' => $request->notes,
            ]);
            $updatedCollaterals[] = $collateral;
        }

        return response()->json([
            'success' => true,
            'message' => count($updatedCollaterals) . ' collateral(s) rejected successfully',
            'data' => $updatedCollaterals,
        ]);
    }

    /**
     * POST /users/{user}/loan-applications/{application}/collaterals/under-review
     * Admin/staff put application collaterals under review.
     */
    public function underReviewApplicationCollateral(Request $request, User $user, LoanApplication $application): JsonResponse
    {
        if (!in_array($application->status, ['draft', 'under_review'])) {
            return response()->json([
                'success' => false,
                'message' => 'Collateral under-review is only allowed for applications in draft or under_review status.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'collateral_ids' => 'nullable|array',
            'collateral_ids.*' => 'uuid|exists:loan_collaterals,id',
            'collateral_id' => 'nullable|uuid|exists:loan_collaterals,id',
            'collateral_type' => 'nullable|string',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Check if bulk operation
        if ($request->filled('collateral_ids')) {
            $collateralQuery = $application->collaterals()->whereIn('id', $request->collateral_ids);
        } elseif ($request->filled('collateral_id')) {
            $collateralQuery = $application->collaterals()->where('id', $request->collateral_id);
        } elseif ($request->filled('collateral_type')) {
            $collateralQuery = $application->collaterals()->where('type', $request->collateral_type);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either collateral_ids (for bulk), collateral_id, or collateral_type is required to put collaterals under review.'
            ], 422);
        }

        $collaterals = $collateralQuery->get();
        if ($collaterals->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No collaterals found matching the criteria.'
            ], 404);
        }

        $updatedCollaterals = [];
        foreach ($collaterals as $collateral) {
            $collateral->update([
                'status' => 'under_review',
                'verification_notes' => $request->notes,
            ]);
            $updatedCollaterals[] = $collateral;
        }

        return response()->json([
            'success' => true,
            'message' => count($updatedCollaterals) . ' collateral(s) put under review successfully',
            'data' => $updatedCollaterals,
        ]);
    }

    /**
     * POST /users/{user}/loan-applications/{application}/guarantors/verify
     * Admin/staff verify application guarantors.
     */
    public function verifyApplicationGuarantor(Request $request, User $user, LoanApplication $application): JsonResponse
    {
        if (!in_array($application->status, ['draft', 'under_review'])) {
            return response()->json([
                'success' => false,
                'message' => 'Guarantor verification is only allowed for applications in draft or under_review status.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'guarantor_ids' => 'nullable|array',
            'guarantor_ids.*' => 'uuid|exists:loan_guarantors,id',
            'guarantor_id' => 'nullable|uuid|exists:loan_guarantors,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Check if bulk operation
        if ($request->filled('guarantor_ids')) {
            $guarantorQuery = $application->guarantors()->whereIn('id', $request->guarantor_ids);
        } elseif ($request->filled('guarantor_id')) {
            $guarantorQuery = $application->guarantors()->where('id', $request->guarantor_id);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either guarantor_ids (for bulk) or guarantor_id is required to verify guarantors.'
            ], 422);
        }

        $guarantors = $guarantorQuery->get();
        if ($guarantors->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No guarantors found matching the criteria.'
            ], 404);
        }

        $updatedGuarantors = [];
        foreach ($guarantors as $guarantor) {
            $guarantor->update([
                'status' => 'verified',
                'notes' => $request->notes,
            ]);
            $updatedGuarantors[] = $guarantor;
        }

        return response()->json([
            'success' => true,
            'message' => count($updatedGuarantors) . ' guarantor(s) verified successfully',
            'data' => $updatedGuarantors,
        ]);
    }

    /**
     * POST /users/{user}/loan-applications/{application}/guarantors/reject
     * Admin/staff reject application guarantors.
     */
    public function rejectApplicationGuarantor(Request $request, User $user, LoanApplication $application): JsonResponse
    {
        if (!in_array($application->status, ['draft', 'under_review'])) {
            return response()->json([
                'success' => false,
                'message' => 'Guarantor rejection is only allowed for applications in draft or under_review status.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'guarantor_ids' => 'nullable|array',
            'guarantor_ids.*' => 'uuid|exists:loan_guarantors,id',
            'guarantor_id' => 'nullable|uuid|exists:loan_guarantors,id',
            'rejection_reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Check if bulk operation
        if ($request->filled('guarantor_ids')) {
            $guarantorQuery = $application->guarantors()->whereIn('id', $request->guarantor_ids);
        } elseif ($request->filled('guarantor_id')) {
            $guarantorQuery = $application->guarantors()->where('id', $request->guarantor_id);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either guarantor_ids (for bulk) or guarantor_id is required to reject guarantors.'
            ], 422);
        }

        $guarantors = $guarantorQuery->get();
        if ($guarantors->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No guarantors found matching the criteria.'
            ], 404);
        }

        $updatedGuarantors = [];
        foreach ($guarantors as $guarantor) {
            $guarantor->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'notes' => $request->notes,
            ]);
            $updatedGuarantors[] = $guarantor;
        }

        return response()->json([
            'success' => true,
            'message' => count($updatedGuarantors) . ' guarantor(s) rejected successfully',
            'data' => $updatedGuarantors,
        ]);
    }

    /**
     * POST /users/{user}/loan-applications/{application}/guarantors/under-review
     * Admin/staff put application guarantors under review.
     */
    public function underReviewApplicationGuarantor(Request $request, User $user, LoanApplication $application): JsonResponse
    {
        if (!in_array($application->status, ['draft', 'under_review'])) {
            return response()->json([
                'success' => false,
                'message' => 'Guarantor under-review is only allowed for applications in draft or under_review status.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'guarantor_ids' => 'nullable|array',
            'guarantor_ids.*' => 'uuid|exists:loan_guarantors,id',
            'guarantor_id' => 'nullable|uuid|exists:loan_guarantors,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Check if bulk operation
        if ($request->filled('guarantor_ids')) {
            $guarantorQuery = $application->guarantors()->whereIn('id', $request->guarantor_ids);
        } elseif ($request->filled('guarantor_id')) {
            $guarantorQuery = $application->guarantors()->where('id', $request->guarantor_id);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either guarantor_ids (for bulk) or guarantor_id is required to put guarantors under review.'
            ], 422);
        }

        $guarantors = $guarantorQuery->get();
        if ($guarantors->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No guarantors found matching the criteria.'
            ], 404);
        }

        $updatedGuarantors = [];
        foreach ($guarantors as $guarantor) {
            $guarantor->update([
                'status' => 'under_review',
                'notes' => $request->notes,
            ]);
            $updatedGuarantors[] = $guarantor;
        }

        return response()->json([
            'success' => true,
            'message' => count($updatedGuarantors) . ' guarantor(s) put under review successfully',
            'data' => $updatedGuarantors,
        ]);
    }

    // ================== CUSTOMER LOAN REPAYMENT METHODS ==================

    /**
     * GET /user/loans/{id}/schedules
     */
    public function userLoanSchedules(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        // Verify loan belongs to user
        $loan = Loan::where('id', $id)
            ->where('customer_id', $user->id)
            ->firstOrFail();

        $schedule = $this->loanService->getSchedule($id);
        return response()->json(['success' => true, 'data' => $schedule]);
    }

    /**
     * GET /user/loans/{id}/repayments
     */
    public function userLoanRepayments(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        // Verify loan belongs to user
        $loan = Loan::where('id', $id)
            ->where('customer_id', $user->id)
            ->firstOrFail();

        $repayments = $loan->repayments()
            ->orderBy('paid_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $repayments]);
    }

    /**
     * POST /user/loans/{id}/repayments
     */
    public function userLoanRepayment(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        // Verify loan belongs to user
        $loan = Loan::where('id', $id)
            ->where('customer_id', $user->id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'source_account_id' => 'required|uuid|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|in:partial,payoff',
            'reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Verify source account belongs to user and is a wallet
        $sourceAccount = Account::where('id', $request->source_account_id)
            ->where('customer_id', $user->id)
            ->whereHas('accountType', function ($query) {
                $query->where('code', 'WAL');
            })
            ->first();

        if (!$sourceAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid source account. Must be a wallet account owned by you.'
            ], 422);
        }

        // Check sufficient balance
        $balance = $sourceAccount->balance;
        if (!$balance || $balance->available_balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance in wallet account.'
            ], 422);
        }

        try {
            if ($request->payment_type === 'payoff') {
                // Calculate payoff amount
                $payoffAmount = $this->loanService->calculatePayoffAmount($id);
                $repayment = $this->loanService->recordRepayment(
                    $id,
                    $payoffAmount,
                    $sourceAccount->id,
                    'wallet',
                    $request->reference
                );
            } else {
                $repayment = $this->loanService->recordRepayment(
                    $id,
                    $request->amount,
                    $sourceAccount->id,
                    'wallet',
                    $request->reference
                );
            }

            $loan = $this->loanService->allocateRepayment($repayment->id);

            // Post ledger entries for the repayment
            $this->loanService->postRepaymentLedgerEntries($repayment);

            return response()->json([
                'success' => true,
                'message' => 'Loan repayment processed successfully',
                'data' => $repayment->load('loan')
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * GET /user/loans/{id}/payoff-quote
     */
    public function userLoanPayoffQuote(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        // Verify loan belongs to user
        $loan = Loan::where('id', $id)
            ->where('customer_id', $user->id)
            ->firstOrFail();

        $payoffQuote = $this->loanService->getPayoffQuote($id);

        return response()->json(['success' => true, 'data' => $payoffQuote]);
    }

    /**
     * POST /user/loans/{id}/payoff
     */
    public function userLoanPayoff(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        // Verify loan belongs to user
        $loan = Loan::where('id', $id)
            ->where('customer_id', $user->id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'source_account_id' => 'nullable|uuid|exists:accounts,id',
            'reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $sourceAccountId = null;

        if ($request->filled('source_account_id')) {
            // Verify source account belongs to user and is a wallet
            $sourceAccount = Account::where('id', $request->source_account_id)
                ->where('customer_id', $user->id)
                ->whereHas('accountType', function ($query) {
                    $query->where('code', 'WAL');
                })
                ->first();

            if (!$sourceAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid source account. Must be a wallet account owned by you.'
                ], 422);
            }

            $sourceAccountId = $sourceAccount->id;
        }

        try {
            $loan = $this->loanService->payoffLoan(
                $id,
                $sourceAccountId,
                $sourceAccountId ? 'wallet' : null,
                $request->reference
            );

            return response()->json([
                'success' => true,
                'message' => 'Loan payoff processed',
                'data' => $loan
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    // ================== ADMIN METHODS ==================

    /**
     * POST /admin/wallets/{walletId}/deposits
     */
    public function depositToWallet(Request $request, string $walletId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,bank_transfer,card,other',
            'reference' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Verify wallet exists and is active
        $wallet = Account::where('id', $walletId)
            ->whereHas('accountType', function ($query) {
                $query->where('code', 'WAL');
            })
            ->where('status', 'active')
            ->first();

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found or inactive.'
            ], 404);
        }

        try {
            // Credit the wallet
            $account = app(\App\Services\Account\AccountService::class)->credit(
                $wallet->id,
                $request->amount,
                "Wallet deposit - {$request->payment_method}: {$request->reference}",
                'wallet_deposit',
                null
            );

            // Create a deposit record (you might want to create a Deposit model for this)
            // For now, we'll just return the updated account

            return response()->json([
                'success' => true,
                'message' => 'Wallet deposit processed successfully',
                'data' => $account
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process wallet deposit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /admin/loans/{loanId}/repayments
     */
    public function adminLoanRepayment(Request $request, string $loanId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,bank_transfer,card,other',
            'external_reference' => 'required|string|max:255',
            'skip_wallet' => 'boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $repayment = $this->loanService->recordRepayment(
                $loanId,
                $request->amount,
                null, // No source account for direct repayment
                $request->payment_method,
                $request->external_reference,
                true, // Direct to repay account
                $request->skip_wallet ?? true
            );

            $loan = $this->loanService->allocateRepayment($repayment->id);

            // Post ledger entries for the repayment
            $this->loanService->postRepaymentLedgerEntries($repayment);

            return response()->json([
                'success' => true,
                'message' => 'Loan repayment processed successfully',
                'data' => $repayment->load('loan')
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }
}

