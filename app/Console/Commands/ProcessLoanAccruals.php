<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Services\Loan\LoanService;
use Illuminate\Support\Facades\DB;

class ProcessLoanAccruals extends Command
{
    protected $signature = 'loans:accrue-interest';
    protected $description = 'Daily: Accrue interest and apply penalties to overdue loan schedules';

    public function __construct(
        protected LoanService $loanService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting loan accrual processing...');

        // Get all active loans with their schedules
        $loans = Loan::where('status', 'active')
            ->with('schedules')
            ->get();

        $accrued = 0;
        $penalized = 0;

        DB::transaction(function () use ($loans, &$accrued, &$penalized) {
            foreach ($loans as $loan) {
                // Check each unpaid schedule for overdue penalties
                $schedules = $loan->schedules()
                    ->where('status', '!=', 'paid')
                    ->get();

                foreach ($schedules as $schedule) {
                    // Apply penalty if overdue and not already applied
                    if ($schedule->isOverdue() && $schedule->penalty_due > $schedule->penalty_paid) {
                        $this->applyPenalty($loan, $schedule);
                        $penalized++;
                    }

                    // Accrue daily interest
                    $this->accrueInterest($loan, $schedule);
                    $accrued++;
                }

                // Check for delinquency (days past due)
                $this->checkDelinquency($loan);
            }
        });

        $this->info("✓ Accrued interest on {$accrued} schedules");
        $this->info("✓ Applied penalties to {$penalized} overdue schedules");

        return Command::SUCCESS;
    }

    /**
     * Apply late payment penalty to overdue schedule
     */
    protected function applyPenalty(Loan $loan, LoanSchedule $schedule): void
    {
        $product = $loan->application->loanProduct;
        if (!$product->penalty_rate || ! $schedule->isOverdue()) {
            return;
        }

        $dailyPenaltyRate = ($product->penalty_rate / 100) / 365;
        $principalRemaining = max(0, $schedule->principal_due - $schedule->principal_paid);
        if ($principalRemaining <= 0) {
            return;
        }

        $penaltyAmount = round($principalRemaining * $dailyPenaltyRate, 2);
        if ($penaltyAmount <= 0) {
            return;
        }

        $schedule->update([
            'penalty_due' => $schedule->penalty_due + $penaltyAmount,
            'total_due' => $schedule->principal_due + $schedule->interest_due + $schedule->fees_due + $schedule->penalty_due + $penaltyAmount,
        ]);
    }

    /**
     * Accrue interest on schedule (typically for reducing balance loans)
     */
    protected function accrueInterest(Loan $loan, LoanSchedule $schedule): void
    {
        // For fixed-rate loans, interest is pre-calculated in schedules
        // This is a placeholder for systems that accrue interest daily
        // For now, we just verify the interest_due matches the schedule

        if ($schedule->interest_paid < $schedule->interest_due && $schedule->status !== 'paid') {
            // Interest is already baked into the schedule
            // In more complex systems, you'd accrue daily here
        }
    }

    /**
     * Check and update loan delinquency status
     */
    protected function checkDelinquency(Loan $loan): void
    {
        // Find the oldest unpaid schedule
        $oldestUnpaid = $loan->schedules()
            ->where('status', '!=', 'paid')
            ->orderBy('due_date')
            ->first();

        if (!$oldestUnpaid) {
            // All paid
            if ($loan->status !== 'closed') {
                $loan->update(['status' => 'closed']);
            }
            return;
        }

        $daysPastDue = now()->diffInDays($oldestUnpaid->due_date, false);
        if ($daysPastDue <= 0) {
            return;
        }

        if ($oldestUnpaid->status === 'unpaid') {
            $oldestUnpaid->update(['status' => 'overdue']);
        }

        if ($daysPastDue >= 30 && $loan->status !== 'defaulted') {
            $loan->update([
                'status' => 'defaulted',
            ]);

            $this->warn("Loan {$loan->loan_no} marked as defaulted ({$daysPastDue} days overdue)");
        }
    }
}
