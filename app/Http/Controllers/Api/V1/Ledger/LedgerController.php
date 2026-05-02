<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Http\Controllers\Controller;
use App\Models\DailyClosing;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Services\Ledger\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LedgerController extends Controller
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * GET /ledgers - List all ledger accounts
     */
    public function index(Request $request): JsonResponse
    {
        $query = LedgerAccount::query()
            ->with(['parent', 'children'])
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->parent_id, fn($q, $p) => $q->where('parent_id', $p))
            ->when($request->active === 'false', fn($q) => $q->where('active', false))
            ->when(!$request->include_inactive, fn($q) => $q->active());

        $accounts = $query->orderBy('code')->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    /**
     * GET /ledgers/{id} - Get ledger account details
     */
    public function show(string $id): JsonResponse
    {
        $account = LedgerAccount::with(['parent', 'children'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $account,
        ]);
    }

    /**
     * POST /ledgers - Create ledger account
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:20|unique:ledger_accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,income,expense',
            'parent_id' => 'nullable|uuid|exists:ledger_accounts,id',
            'currency' => 'nullable|string|size:3',
            'allow_manual_entry' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $account = LedgerAccount::create([
            ...$request->validated(),
            'level' => $request->parent_id ? 2 : 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ledger account created successfully',
            'data' => $account,
        ], 201);
    }

    /**
     * PUT /ledgers/{id} - Update ledger account
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $account = LedgerAccount::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:asset,liability,equity,income,expense',
            'parent_id' => 'nullable|uuid|exists:ledger_accounts,id',
            'active' => 'nullable|boolean',
            'allow_manual_entry' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $account->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Ledger account updated successfully',
            'data' => $account->fresh(),
        ]);
    }

    /**
     * GET /ledgers/{id}/transactions - Get account transactions
     */
    public function transactions(Request $request, string $id): JsonResponse
    {
        $account = LedgerAccount::findOrFail($id);

        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();

        $statement = $this->ledgerService->getAccountStatement(
            $id,
            \Carbon\Carbon::parse($startDate),
            \Carbon\Carbon::parse($endDate)
        );

        return response()->json([
            'success' => true,
            'data' => $statement,
        ]);
    }

    /**
     * GET /ledgers/{id}/statement - Get account statement (alias for transactions)
     */
    public function statement(Request $request, string $id): JsonResponse
    {
        return $this->transactions($request, $id);
    }

    /**
     * GET /ledger/trial-balance - Get trial balance
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date) : now()->startOfMonth();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date) : now()->endOfMonth();

        $trialBalance = $this->ledgerService->getTrialBalance($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $trialBalance,
        ]);
    }

    /**
     * GET /ledger/gl - Get general ledger
     */
    public function generalLedger(Request $request): JsonResponse
    {
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();

        $entries = JournalEntry::where('status', 'posted')
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->with(['lines.ledgerAccount', 'postedByUser'])
            ->when($request->ledger_account_id, fn($q, $id) => 
                $q->whereHas('lines', fn($l) => $l->where('ledger_account_id', $id))
            )
            ->orderBy('entry_date')
            ->orderBy('reference')
            ->paginate($request->per_page ?? 50);

        return response()->json([
            'success' => true,
            'data' => $entries,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * POST /ledger/journals - Create journal entry
     */
    public function createJournal(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required|string',
            'entry_date' => 'nullable|date',
            'source_type' => 'nullable|string',
            'source_id' => 'nullable|uuid',
            'lines' => 'required|array|min:2',
            'lines.*.ledger_account_id' => 'required|uuid|exists:ledger_accounts,id',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'lines.*.narration' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $entry = $this->ledgerService->createJournalEntry(
                $request->lines,
                $request->description,
                $request->source_type,
                $request->source_id,
                $request->entry_date ? \Carbon\Carbon::parse($request->entry_date) : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Journal entry created successfully',
                'data' => $entry->load('lines.ledgerAccount'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * GET /ledger/journals - List journal entries
     */
    public function journals(Request $request): JsonResponse
    {
        $entries = JournalEntry::with(['lines.ledgerAccount', 'postedByUser'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->source_type, fn($q, $t) => $q->where('source_type', $t))
            ->when($request->start_date, fn($q, $d) => $q->where('entry_date', '>=', $d))
            ->when($request->end_date, fn($q, $d) => $q->where('entry_date', '<=', $d))
            ->orderBy('entry_date', 'desc')
            ->orderBy('reference', 'desc')
            ->paginate($request->per_page ?? 50);

        return response()->json([
            'success' => true,
            'data' => $entries,
        ]);
    }

    /**
     * GET /ledger/journals/{id} - Get journal entry details
     */
    public function showJournal(string $id): JsonResponse
    {
        $entry = JournalEntry::with(['lines.ledgerAccount', 'postedByUser', 'reversalOf'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $entry,
        ]);
    }

    /**
     * POST /ledger/journals/{id}/reverse - Reverse a journal entry
     */
    public function reverseJournal(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $reversalEntry = $this->ledgerService->reverseJournalEntry($id, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Journal entry reversed successfully',
                'data' => $reversalEntry->load('lines.ledgerAccount'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * POST /ledger/close-day - Close a day's transactions
     */
    public function closeDay(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $date = $request->date 
            ? \Carbon\Carbon::parse($request->date) 
            : now()->subDay();

        try {
            $closing = $this->ledgerService->closeDay($date);

            return response()->json([
                'success' => true,
                'message' => 'Day closed successfully',
                'data' => $closing,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * GET /ledger/close-day - Check if day is closed
     */
    public function checkDayClosed(Request $request): JsonResponse
    {
        $date = $request->date ?? now()->toDateString();
        $isClosed = DailyClosing::isDateClosed($date);

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'is_closed' => $isClosed,
            ],
        ]);
    }
}