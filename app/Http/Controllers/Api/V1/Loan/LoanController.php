<?php

namespace App\Http\Controllers\Api\V1\Loan;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\Loan\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $application = LoanApplication::with(['loanProduct', 'customer', 'guarantors', 'collaterals'])
            ->findOrFail($id);
        return response()->json(['success' => true, 'data' => $application]);
    }

    /**
     * POST /loan-applications
     */
    public function createApplication(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|uuid|exists:users,id',
            'loan_product_id' => 'required|uuid|exists:loan_products,id',
            'account_id' => 'nullable|uuid|exists:accounts,id',
            'requested_amount' => 'required|numeric|min:1',
            'requested_tenure' => 'required|integer|min:1',
            'monthly_income' => 'nullable|numeric|min:0',
            'employment_status' => 'nullable|string',
            'purpose' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $application = $this->loanService->createApplication(
                $request->customer_id,
                $request->loan_product_id,
                $request->requested_amount,
                $request->requested_tenure,
                $request->account_id,
                $request->purpose,
                $request->monthly_income,
                $request->employment_status
            );

            return response()->json(['success' => true, 'message' => 'Application created', 'data' => $application], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * POST /loan-applications/{id}/submit
     */
    public function submitApplication(string $id): JsonResponse
    {
        try {
            $application = $this->loanService->submitApplication($id);
            return response()->json(['success' => true, 'message' => 'Application submitted', 'data' => $application]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
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
            return response()->json(['success' => true, 'message' => 'Loan approved', 'data' => $loan], 201);
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
            $repayment = $this->loanService->recordRepayment(
                $id,
                $request->amount,
                $request->account_id,
                $request->payment_channel,
                $request->reference
            );

            // Auto-allocate
            $loan = $this->loanService->allocateRepayment($repayment->id);

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

    /**
     * GET /users/{user_id}/loans-applications/{id}
     */
    public function userApplicationDetail(Request $request, string $userId, string $id): JsonResponse
    {
        $user = User::findOrFail($userId);
        Gate::authorize('view', $user);

        $application = LoanApplication::with(['loanProduct', 'guarantors', 'collaterals'])
            ->where('customer_id', $userId)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $application]);
    }

    /**
     * GET /users/{user_id}/loans/
     */
    public function userLoans(Request $request, string $userId): JsonResponse
    {
        $loans = Loan::with(['application.loanProduct'])
            ->where('customer_id', $userId)
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $loans]);
    }

    /**
     * GET /users/{user_id}/loans/{id}
     */
    public function userLoanDetail(Request $request, string $userId, string $id): JsonResponse
    {
        $loan = Loan::with(['application.loanProduct', 'schedules', 'repayments'])
            ->where('customer_id', $userId)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $loan]);
    }
}

