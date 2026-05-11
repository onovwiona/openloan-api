<?php

namespace App\Services\Ledger;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LedgerService
{
    /**
     * Create a journal entry with double-entry validation
     * 
     * @param array $lines Array of ['ledger_account_id' => string, 'debit' => float, 'credit' => float, 'narration' => string]
     * @param string $description
     * @param string|null $sourceType
     * @param string|null $sourceId
     * @param \DateTimeInterface|null $entryDate
     * @return JournalEntry
     * @throws ValidationException
     */
    public function createJournalEntry(
        array $lines,
        string $description,
        ?string $sourceType = null,
        ?string $sourceId = null,
        ?\DateTimeInterface $entryDate = null,
        ?string $postedBy = null
    ): JournalEntry {
        // Validate double-entry rule
        $this->validateDoubleEntry($lines);

        return DB::transaction(function () use ($lines, $description, $sourceType, $sourceId, $entryDate, $postedBy) {
            // Create the journal entry
            $entry = JournalEntry::create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'reference' => JournalEntry::generateReference(),
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => $description,
                'entry_date' => $entryDate ?? now()->toDateString(),
                'posted_by' => $postedBy ?? auth()->id() ?? '00000000-0000-0000-0000-000000000000', // Default UUID for system
                'status' => 'posted',
            ]);

            // Create journal lines
            foreach ($lines as $line) {
                JournalLine::create([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'journal_entry_id' => $entry->id,
                    'ledger_account_id' => $line['ledger_account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'narration' => $line['narration'] ?? $description,
                ]);
            }

            return $entry->fresh(['lines', 'postedByUser']);
        });
    }

    /**
     * Validate that debits equal credits (double-entry rule)
     * 
     * @throws ValidationException
     */
    protected function validateDoubleEntry(array $lines): void
    {
        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($lines as $line) {
            if (!isset($line['ledger_account_id'])) {
                throw ValidationException::withMessages([
                    'lines' => 'Each journal line must have a ledger_account_id'
                ]);
            }

            // Verify ledger account exists
            $account = LedgerAccount::find($line['ledger_account_id']);
            if (!$account) {
                throw ValidationException::withMessages([
                    'ledger_account_id' => "Ledger account not found: {$line['ledger_account_id']}"
                ]);
            }

            $totalDebits += $line['debit'] ?? 0;
            $totalCredits += $line['credit'] ?? 0;
        }

        // Allow for floating point precision (round to 2 decimal places)
        $totalDebits = round($totalDebits, 2);
        $totalCredits = round($totalCredits, 2);

        if ($totalDebits !== $totalCredits) {
            throw ValidationException::withMessages([
                'amount' => "Journal entry is not balanced. Debits: {$totalDebits}, Credits: {$totalCredits}"
            ]);
        }

        if ($totalDebits <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Journal entry must have a positive amount'
            ]);
        }
    }

    /**
     * Create a simple debit/credit transaction
     */
    public function createTransaction(
        string $debitAccountId,
        string $creditAccountId,
        float $amount,
        string $description,
        ?string $sourceType = null,
        ?string $sourceId = null
    ): JournalEntry {
        $lines = [
            [
                'ledger_account_id' => $debitAccountId,
                'debit' => $amount,
                'credit' => 0,
                'narration' => $description,
            ],
            [
                'ledger_account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $amount,
                'narration' => $description,
            ],
        ];

        return $this->createJournalEntry($lines, $description, $sourceType, $sourceId);
    }

    /**
     * Reverse a journal entry
     */
    public function reverseJournalEntry(string $entryId, string $reason): JournalEntry
    {
        $originalEntry = JournalEntry::findOrFail($entryId);

        if ($originalEntry->status !== 'posted') {
            throw ValidationException::withMessages([
                'status' => 'Only posted journal entries can be reversed'
            ]);
        }

        return DB::transaction(function () use ($originalEntry, $reason) {
            // Create reversal entry
            $reversalLines = $originalEntry->lines->map(function ($line) {
                return [
                    'ledger_account_id' => $line->ledger_account_id,
                    'debit' => $line->credit, // Swap debit/credit
                    'credit' => $line->debit,
                    'narration' => "Reversal: {$line->narration}",
                ];
            })->toArray();

            $reversalEntry = JournalEntry::create([
                'reference' => JournalEntry::generateReference('REV'),
                'source_type' => 'reversal',
                'source_id' => $originalEntry->id,
                'description' => "Reversal of {$originalEntry->reference}: {$reason}",
                'entry_date' => now()->toDateString(),
                'posted_by' => auth()->id(),
                'status' => 'posted',
                'reversal_of' => $originalEntry->id,
            ]);

            foreach ($reversalLines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $reversalEntry->id,
                    'ledger_account_id' => $line['ledger_account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'narration' => $line['narration'],
                ]);
            }

            // Mark original as reversed
            $originalEntry->update([
                'status' => 'reversed',
                'reversed_by' => auth()->id(),
            ]);

            return $reversalEntry->fresh(['lines']);
        });
    }

    /**
     * Get trial balance for a date range
     */
    public function getTrialBalance(?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null)
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        $accounts = LedgerAccount::active()->with(['lines' => function ($query) use ($startDate, $endDate) {
            $query->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('entry_date', [$startDate->toDateString(), $endDate->toDateString()])
                  ->where('status', 'posted');
            });
        }])->get();

        $trialBalance = [];
        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($accounts as $account) {
            $debitTotal = $account->lines->sum('debit');
            $creditTotal = $account->lines->sum('credit');

            // Calculate balance based on account type
            $balance = $this->calculateAccountBalance($account->type, $debitTotal, $creditTotal);

            $trialBalance[] = [
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'account_type' => $account->type,
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
                'balance' => $balance,
            ];

            $totalDebits += $debitTotal;
            $totalCredits += $creditTotal;
        }

        return [
            'accounts' => $trialBalance,
            'summary' => [
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'balanced' => round($totalDebits, 2) === round($totalCredits, 2),
            ],
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ];
    }

    /**
     * Calculate account balance based on type
     */
    protected function calculateAccountBalance(string $type, float $debitTotal, float $creditTotal): float
    {
        return match ($type) {
            'asset', 'expense' => $debitTotal - $creditTotal,
            'liability', 'equity', 'income' => $creditTotal - $debitTotal,
            default => $debitTotal - $creditTotal,
        };
    }

    /**
     * Get account statement (transactions for a specific account)
     */
    public function getAccountStatement(
        string $accountId,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ) {
        $account = LedgerAccount::findOrFail($accountId);
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        $lines = JournalLine::where('ledger_account_id', $accountId)
            ->whereHas('journalEntry', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('entry_date', [$startDate->toDateString(), $endDate->toDateString()])
                      ->where('status', 'posted');
            })
            ->with('journalEntry')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->select('journal_lines.*')
            ->get();

        $runningBalance = 0;
        $transactions = $lines->map(function ($line) use ($account, &$runningBalance) {
            // Calculate running balance based on account type
            if (in_array($account->type, ['asset', 'expense'])) {
                $runningBalance += $line->debit - $line->credit;
            } else {
                $runningBalance += $line->credit - $line->debit;
            }

            return [
                'date' => $line->journalEntry->entry_date,
                'reference' => $line->journalEntry->reference,
                'description' => $line->narration,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'running_balance' => $runningBalance,
            ];
        });

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
            ],
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'transactions' => $transactions,
            'closing_balance' => $runningBalance,
        ];
    }

    /**
     * Close a day's transactions
     */
    public function closeDay(\DateTimeInterface $date): DailyClosing
    {
        // Check if already closed
        if (DailyClosing::isDateClosed($date)) {
            throw ValidationException::withMessages([
                'date' => 'This date is already closed'
            ]);
        }

        // Get all posted entries for the date
        $entries = JournalEntry::where('entry_date', $date->toDateString())
            ->where('status', 'posted')
            ->with('lines')
            ->get();

        $totalDebits = $entries->flatMap->lines->sum('debit');
        $totalCredits = $entries->flatMap->lines->sum('credit');

        return DailyClosing::create([
            'closing_date' => $date->toDateString(),
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'balanced' => round($totalDebits, 2) === round($totalCredits, 2),
            'closed_by' => auth()->id(),
        ]);
    }
}