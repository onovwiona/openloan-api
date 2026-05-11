<?php

namespace App\Services\Account;

use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\AccountLimit;
use App\Models\LedgerAccount;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Create a new customer account
     */
    public function createAccount(
        string $customerId,
        string $accountTypeId,
        ?string $name = null
    ): Account {
        $accountType = \App\Models\AccountType::findOrFail($accountTypeId);

        return DB::transaction(function () use ($customerId, $accountType, $name) {
            // Create the account
            $account = Account::create([
                'id' => (string) Str::uuid(),
                'customer_id' => $customerId,
                'account_type_id' => $accountType->id,
                'account_no' => Account::generateAccountNumber($accountType->code),
                'name' => $name ?? "{$accountType->name} Account",
                'currency' => $accountType->currency,
                'status' => 'active',
                'opened_at' => now()->toDateString(),
            ]);

            // Create initial balance record
            AccountBalance::create([
                'id' => (string) Str::uuid(),
                'account_id' => $account->id,
                'available_balance' => 0,
                'ledger_balance' => 0,
                'hold_balance' => 0,
                'uncleared_balance' => 0,
                'as_at' => now(),
            ]);

            // Create default limits
            AccountLimit::create([
                'id' => (string) Str::uuid(),
                'account_id' => $account->id,
            ]);

            return $account;
        });
    }

    /**
     * Credit (deposit) money into an account
     */
    public function credit(
        string $accountId,
        float $amount,
        string $description,
        ?string $sourceType = null,
        ?string $sourceId = null
    ): Account {
        $account = Account::findOrFail($accountId);

        if ($account->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => "Cannot credit to account with status: {$account->status}"
            ]);
        }

        return DB::transaction(function () use ($account, $amount, $description, $sourceType, $sourceId) {
            // Get or create ledger account for customer deposits
            $ledgerAccount = $this->getOrCreateCustomerLedgerAccount($account);

            // Find the cash/bank ledger account
            $cashAccount = $this->getCashLedgerAccount();

            // Create journal entry (debit cash, credit customer account)
            $this->ledgerService->createTransaction(
                $cashAccount->id,
                $ledgerAccount->id,
                $amount,
                $description,
                $sourceType,
                $sourceId
            );

            // Update account balance
            $this->updateBalance($account->id, $amount, 'credit');

            return $account->fresh(['balance', 'accountType']);
        });
    }

    /**
     * Debit (withdraw) money from an account
     */
    public function debit(
        string $accountId,
        float $amount,
        string $description,
        ?string $sourceType = null,
        ?string $sourceId = null
    ): Account {
        $account = Account::findOrFail($accountId);

        if ($account->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => "Cannot debit from account with status: {$account->status}"
            ]);
        }

        // Check available balance
        $availableBalance = $account->balance?->available_balance ?? 0;
        $accountType = $account->accountType;

        // Check overdraft limit
        $canOverdraft = $accountType->allow_overdraft && 
            ($availableBalance + $accountType->overdraft_limit) >= $amount;

        if (!$canOverdraft) {
            throw ValidationException::withMessages([
                'balance' => "Insufficient funds. Available: {$availableBalance}"
            ]);
        }

        return DB::transaction(function () use ($account, $amount, $description, $sourceType, $sourceId) {
            // Get or create ledger account for customer deposits
            $ledgerAccount = $this->getOrCreateCustomerLedgerAccount($account);

            // Find the cash/bank ledger account
            $cashAccount = $this->getCashLedgerAccount();

            // Create journal entry (debit customer account, credit cash)
            $this->ledgerService->createTransaction(
                $ledgerAccount->id,
                $cashAccount->id,
                $amount,
                $description,
                $sourceType,
                $sourceId
            );

            // Update account balance
            $this->updateBalance($account->id, $amount, 'debit');

            return $account->fresh(['balance', 'accountType']);
        });
    }

    /**
     * Transfer money between accounts
     */
    public function transfer(
        string $fromAccountId,
        string $toAccountId,
        float $amount,
        string $description
    ): array {
        $fromAccount = Account::findOrFail($fromAccountId);
        $toAccount = Account::findOrFail($toAccountId);

        if ($fromAccount->status !== 'active' || $toAccount->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'Both accounts must be active for transfer'
            ]);
        }

        return DB::transaction(function () use ($fromAccount, $toAccount, $fromAccountId, $toAccountId, $amount, $description) {
            // Debit from source account
            $this->debit($fromAccountId, $amount, "Transfer out: {$description}");

            // Credit to destination account
            $this->credit($toAccountId, $amount, "Transfer in: {$description}", 'transfer');

            return [
                'from_account' => $fromAccount->fresh(['balance']),
                'to_account' => $toAccount->fresh(['balance']),
            ];
        });
    }

    /**
     * Hold (reserve) funds on an account
     */
    public function hold(string $accountId, float $amount, string $reason): AccountBalance
    {
        $account = Account::findOrFail($accountId);

        $availableBalance = $account->balance?->available_balance ?? 0;
        if ($availableBalance < $amount) {
            throw ValidationException::withMessages([
                'balance' => "Insufficient funds to hold. Available: {$availableBalance}"
            ]);
        }

        $balance = $account->balance;
        $balance->update([
            'available_balance' => $balance->available_balance - $amount,
            'hold_balance' => $balance->hold_balance + $amount,
        ]);

        return $balance;
    }

    /**
     * Release (unhold) funds on an account
     */
    public function releaseHold(string $accountId, float $amount): AccountBalance
    {
        $balance = AccountBalance::where('account_id', $accountId)
            ->latest()
            ->firstOrFail();

        if ($balance->hold_balance < $amount) {
            throw ValidationException::withMessages([
                'hold_balance' => "Hold amount exceeds available hold balance"
            ]);
        }

        $balance->update([
            'available_balance' => $balance->available_balance + $amount,
            'hold_balance' => $balance->hold_balance - $amount,
        ]);

        return $balance;
    }

    /**
     * Freeze an account
     */
    public function freeze(string $accountId, string $reason): Account
    {
        $account = Account::findOrFail($accountId);
        $account->freeze($reason);

        return $account;
    }

    /**
     * Unfreeze an account
     */
    public function unfreeze(string $accountId): Account
    {
        $account = Account::findOrFail($accountId);
        $account->unfreeze();

        return $account;
    }

    /**
     * Close an account
     */
    public function close(string $accountId): Account
    {
        $account = Account::findOrFail($accountId);

        $balance = $account->balance?->ledger_balance ?? 0;
        if ($balance != 0) {
            throw ValidationException::withMessages([
                'balance' => "Cannot close account with non-zero balance: {$balance}"
            ]);
        }

        $account->close();

        return $account;
    }

    /**
     * Get account statement
     */
    public function getStatement(
        string $accountId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $account = Account::with('accountType', 'customer')->findOrFail($accountId);

        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->endOfMonth()->toDateString();

        // Get ledger account for this customer account
        $ledgerAccount = $this->getOrCreateCustomerLedgerAccount($account);

        // Get transactions from ledger
        $statement = $this->ledgerService->getAccountStatement(
            $ledgerAccount->id,
            \Carbon\Carbon::parse($startDate),
            \Carbon\Carbon::parse($endDate)
        );

        return [
            'account' => [
                'id' => $account->id,
                'account_no' => $account->account_no,
                'name' => $account->name,
                'type' => $account->accountType->name,
                'status' => $account->status,
            ],
            'balance' => [
                'available' => $account->balance?->available_balance ?? 0,
                'ledger' => $account->balance?->ledger_balance ?? 0,
                'hold' => $account->balance?->hold_balance ?? 0,
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'transactions' => $statement['transactions'],
            'closing_balance' => $statement['closing_balance'],
        ];
    }

    /**
     * Get account transactions
     */
    public function getTransactions(
        string $accountId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $account = Account::with('accountType', 'customer')->findOrFail($accountId);

        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->endOfMonth()->toDateString();

        // Get ledger account for this customer account
        $ledgerAccount = $this->getOrCreateCustomerLedgerAccount($account);

        // Get transactions from ledger
        $statement = $this->ledgerService->getAccountStatement(
            $ledgerAccount->id,
            \Carbon\Carbon::parse($startDate),
            \Carbon\Carbon::parse($endDate)
        );

        return $statement['transactions']->toArray();
    }

    /**
     * Update account balance
     */
    protected function updateBalance(string $accountId, float $amount, string $type): void
    {
        $balance = AccountBalance::where('account_id', $accountId)
            ->latest()
            ->firstOrFail();

        if ($type === 'credit') {
            $balance->update([
                'available_balance' => $balance->available_balance + $amount,
                'ledger_balance' => $balance->ledger_balance + $amount,
            ]);
        } else {
            $balance->update([
                'available_balance' => $balance->available_balance - $amount,
                'ledger_balance' => $balance->ledger_balance - $amount,
            ]);
        }

        $balance->update(['as_at' => now()]);
    }

/**
     * Get or create ledger account for customer deposits
     */
    protected function getOrCreateCustomerLedgerAccount(Account $account): LedgerAccount
    {
        // Look for existing ledger account linked to this account by name
        $existingLedger = LedgerAccount::where('name', 'like', "%Customer Account - {$account->account_no}%")->first();

        if ($existingLedger) {
            return $existingLedger;
        }

        // Generate a unique code based on account_no (alphanumeric only, max 15 chars after prefix)
        $cleanAccountNo = preg_replace('/[^a-zA-Z0-9]/', '', $account->account_no);
        $codeSuffix = substr($cleanAccountNo, -15);
        
        // Ensure uniqueness by checking for existing codes and appending a sequence number if needed
        $baseCode = 'CUS' . $codeSuffix;
        $code = $baseCode;
        $sequence = 0;
        
        while (LedgerAccount::where('code', $code)->exists()) {
            $sequence++;
            $code = $baseCode . $sequence;
        }
        
        // Create a new ledger account for this customer
        $ledgerAccount = LedgerAccount::create([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'name' => "Customer Account - {$account->account_no}",
            'type' => 'asset',
            'active' => true,
            'allow_manual_entry' => true,
        ]);

        return $ledgerAccount;
    }

    /**
     * Get the cash/bank ledger account
     */
    protected function getCashLedgerAccount(): LedgerAccount
    {
        return LedgerAccount::where('code', '1010')->firstOrFail();
    }
}