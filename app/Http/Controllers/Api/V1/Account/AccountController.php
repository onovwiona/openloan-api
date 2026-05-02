<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\User;
use App\Services\Account\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    /**
     * GET /account-types - List account types
     */
    public function types(): JsonResponse
    {
        $types = AccountType::active()->orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $types]);
    }

    /**
     * GET /account-types/{id} - Get account type details
     */
    public function showType(string $id): JsonResponse
    {
        $type = AccountType::findOrFail($id);
        return response()->json(['success' => true, 'data' => $type]);
    }

    /**
     * POST /account-types - Create account type
     */
    public function createType(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:20|unique:account_types,code',
            'name' => 'required|string|max:255',
            'currency' => 'nullable|string|size:3',
            'min_balance' => 'nullable|numeric|min:0',
            'max_balance' => 'nullable|numeric|min:0',
            'allow_overdraft' => 'nullable|boolean',
            'overdraft_limit' => 'nullable|numeric|min:0',
            'accrues_interest' => 'nullable|boolean',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $type = AccountType::create($request->validated());
        return response()->json(['success' => true, 'message' => 'Account type created', 'data' => $type], 201);
    }

    /**
     * GET /accounts - List all accounts (admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Account::with(['accountType', 'customer'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->account_type_id, fn($q, $t) => $q->where('account_type_id', $t))
            ->when($request->customer_id, fn($q, $c) => $q->where('customer_id', $c));

        $accounts = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 20);
        return response()->json(['success' => true, 'data' => $accounts]);
    }

    /**
     * GET /accounts/{id} - Get account details
     */
    public function show(string $id): JsonResponse
    {
        $account = Account::with(['accountType', 'customer', 'balance', 'limits'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $account]);
    }

    /**
     * POST /accounts - Create account
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|uuid|exists:users,id',
            'account_type_id' => 'required|uuid|exists:account_types,id',
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $account = $this->accountService->createAccount(
            $request->customer_id,
            $request->account_type_id,
            $request->name
        );

        return response()->json(['success' => true, 'message' => 'Account created', 'data' => $account], 201);
    }

    /**
     * POST /accounts/{id}/credit - Credit (deposit) to account
     */
    public function credit(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:500',
            'source_type' => 'nullable|string',
            'source_id' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $account = $this->accountService->credit(
                $id,
                $request->amount,
                $request->description,
                $request->source_type,
                $request->source_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Credit successful',
                'data' => $account->load('balance'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * POST /accounts/{id}/debit - Debit (withdraw) from account
     */
    public function debit(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:500',
            'source_type' => 'nullable|string',
            'source_id' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $account = $this->accountService->debit(
                $id,
                $request->amount,
                $request->description,
                $request->source_type,
                $request->source_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Debit successful',
                'data' => $account->load('balance'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * POST /accounts/{id}/transfer - Transfer to another account
     */
    public function transfer(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to_account_id' => 'required|uuid|exists:accounts,id',
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if ($id === $request->to_account_id) {
            return response()->json(['success' => false, 'errors' => ['to_account_id' => ['Cannot transfer to the same account']]], 422);
        }

        try {
            $result = $this->accountService->transfer(
                $id,
                $request->to_account_id,
                $request->amount,
                $request->description
            );

            return response()->json([
                'success' => true,
                'message' => 'Transfer successful',
                'data' => $result,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * GET /accounts/{id}/transactions - Get account transactions
     */
    public function transactions(Request $request, string $id): JsonResponse
    {
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();

        $statement = $this->accountService->getStatement($id, $startDate, $endDate);

        return response()->json(['success' => true, 'data' => $statement]);
    }

    /**
     * GET /accounts/{id}/statement - Get account statement (alias)
     */
    public function statement(Request $request, string $id): JsonResponse
    {
        return $this->transactions($request, $id);
    }

    /**
     * POST /accounts/{id}/freeze - Freeze account
     */
    public function freeze(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $account = $this->accountService->freeze($id, $request->reason);
        return response()->json(['success' => true, 'message' => 'Account frozen', 'data' => $account]);
    }

    /**
     * POST /accounts/{id}/unfreeze - Unfreeze account
     */
    public function unfreeze(string $id): JsonResponse
    {
        $account = $this->accountService->unfreeze($id);
        return response()->json(['success' => true, 'message' => 'Account unfrozen', 'data' => $account]);
    }

    /**
     * POST /accounts/{id}/close - Close account
     */
    public function close(string $id): JsonResponse
    {
        try {
            $account = $this->accountService->close($id);
            return response()->json(['success' => true, 'message' => 'Account closed', 'data' => $account]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
    }

    /**
     * GET /users/{user_id}/accounts - Get user's accounts
     */
    public function userAccounts(Request $request, string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        Gate::authorize('view', $user);

        $accounts = Account::with(['accountType', 'balance'])
            ->where('customer_id', $userId)
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $accounts]);
    }

    /**
     * GET /users/{user_id}/accounts/{id} - Get user's specific account
     */
    public function userAccount(Request $request, string $userId, string $id): JsonResponse
    {
        $user = User::findOrFail($userId);
        Gate::authorize('view', $user);

        $account = Account::with(['accountType', 'balance', 'limits'])
            ->where('customer_id', $userId)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $account]);
    }

    /**
     * GET /users/{user_id}/accounts/{id}/statement - Get user's account statement
     */
    public function userAccountStatement(Request $request, string $userId, string $id): JsonResponse
    {
        $user = User::findOrFail($userId);
        Gate::authorize('view', $user);

        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();

        $statement = $this->accountService->getStatement($id, $startDate, $endDate);

        return response()->json(['success' => true, 'data' => $statement]);
    }
}

