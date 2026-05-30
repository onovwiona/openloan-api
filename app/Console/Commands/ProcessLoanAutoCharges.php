<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\Account;
use App\Services\Loan\LoanService;
use App\Services\Account\AccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessLoanAutoCharges extends Command
{
    protected $signature = 'loans:auto-charge {--dry-run}';
    protected $description = 'Daily: Attempt automatic repayment deductions from customer wallets for overdue schedules';

    public function __construct(
        protected LoanService $loanService,
        protected AccountService $accountService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $this->info('Starting loan auto-charge processing' . ($dryRun ? ' (DRY RUN)' : '') . '...');

        // Get all active loans with delinquent schedules
        $loans = Loan::where('status', 'active')
            ->with(['schedules', 'customer', 'application.loanProduct'])
            ->get();

        $charged = 0;
        $failed = 0;

        foreach ($loans as $loan) {
            // Get overdue schedules
            $delinquent = $loan->schedules()
                ->where('status', 'overdue')
                ->where('paid_at', null)
                ->orderBy('due_date')
                ->get();

            foreach ($delinquent as $schedule) {
                // Calculate remaining amount due for this schedule
                $remaining = $schedule->total_due - $schedule->amount_paid;

                if ($remaining <= 0) {
                    continue;
                }

                // Try to charge customer wallet
                try {
                    $wallet = $this->getCustomerWallet($loan->customer_id);

                    if (!$wallet) {
                        $this->warn("Customer {$loan->customer_id} has no wallet for loan {$loan->loan_no}");
                        $failed++;
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("DRY RUN: Would debit NGN {$remaining} from wallet {$wallet->account_no} for loan {$loan->loan_no}");
                    } else {
                        // Record repayment through the wallet and loan repayment settlement account.
                        $repayment = $this->loanService->recordRepayment(
                            $loan->id,
                            $remaining,
                            $wallet->id,
                            'auto_wallet_deduction',
                            "Auto-charge for schedule {$schedule->installment_no}"
                        );

                        // Allocate to schedule and post ledger entries
                        $this->loanService->allocateRepayment($repayment->id);
                        $this->loanService->postRepaymentLedgerEntries($repayment);

                        $this->info("✓ Auto-charged NGN {$remaining} from wallet for loan {$loan->loan_no}");
                        $charged++;
                    }
                } catch (ValidationException $e) {
                    $this->error("Failed to charge loan {$loan->loan_no}: " . json_encode($e->errors()));
                    $failed++;
                } catch (\Exception $e) {
                    $this->error("Error charging loan {$loan->loan_no}: {$e->getMessage()}");
                    $failed++;
                }
            }
        }

        $this->info("✓ Processed {$charged} automatic charges");
        if ($failed > 0) {
            $this->warn("✗ Failed to charge {$failed} loans");
        }

        return Command::SUCCESS;
    }

    /**
     * Get customer's default wallet
     */
    protected function getCustomerWallet(string $customerId): ?Account
    {
        return Account::where('customer_id', $customerId)
            ->where('status', 'active')
            ->whereHas('accountType', fn($q) => $q->where('code', 'WAL'))
            ->first();
    }
}
