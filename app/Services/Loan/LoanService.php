<?php

namespace App\Services\Loan;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanSchedule;
use App\Services\Account\AccountService;
use App\Services\Ledger\LedgerService;
use App\Models\AccountType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;



class LoanService
{
    public function __construct(
        protected AccountService $accountService,
        protected LedgerService $ledgerService
    ) {}

    /**
     * Create a loan application
     */
    public function createApplication(
        string $customerId,
        string $productId,
        float $amount,
        int $tenure,
        ?string $accountId = null,
        ?string $purpose = null,
        ?float $monthlyIncome = null,
        ?string $employmentStatus = null
    ): LoanApplication {
        $product = LoanProduct::findOrFail($productId);

        // Prevent duplicate draft applications for the same loan repay account
        if ($accountId) {
            $existingDraft = LoanApplication::where('account_id', $accountId)
                ->where('status', 'draft')
                ->first();

            if ($existingDraft) {
                throw ValidationException::withMessages([
                    'account_id' => ['Another application of this type is already started for this account.']
                ]);
            }
        }

        // Validate amount and tenure
        if ($amount < $product->min_amount || $amount > $product->max_amount) {
            throw ValidationException::withMessages([
                'amount' => "Amount must be between {$product->min_amount} and {$product->max_amount}"
            ]);
        }

        if ($tenure < $product->tenure_min_months || $tenure > $product->tenure_max_months) {
            throw ValidationException::withMessages([
                'tenure' => "Tenure must be between {$product->tenure_min_months} and {$product->tenure_max_months} months"
            ]);
        }

        return DB::transaction(function () use ($customerId, $product, $amount, $tenure, $accountId, $purpose, $monthlyIncome, $employmentStatus) {
            $application = LoanApplication::create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'customer_id' => $customerId,
                'loan_product_id' => $product->id,
                'account_id' => $accountId,
                'application_no' => LoanApplication::generateApplicationNumber(),
                'requested_amount' => $amount,
                'requested_tenure' => $tenure,
                'monthly_income' => $monthlyIncome,
                'employment_status' => $employmentStatus,
                'purpose' => $purpose,
                'status' => 'draft',
            ]);

            // Auto create LOAN_REPAY account if not provided
            if (!$accountId) {
                $repayType = \App\Models\AccountType::where('code', 'LOAN_REPAY')->firstOrFail();
                $repayAccount = $this->accountService->createAccount($customerId, $repayType->id, 'Loan Repay - ' . $application->application_no);
                $application->account_id = $repayAccount->id;
                $application->save();
            }

            return $application;
        });
    }

    /**
     * Submit a loan application
     */
    public function submitApplication(string $applicationId): LoanApplication
    {
        $application = LoanApplication::findOrFail($applicationId);
        $application->submit();

        return $application->fresh();
    }

    /**
     * Cancel a loan application
     */
    public function cancelApplication(string $applicationId): LoanApplication
    {
        $application = LoanApplication::findOrFail($applicationId);
        $application->cancel();

        return $application->fresh();
    }

    /**
     * Approve a loan application
     */
    public function approveApplication(string $applicationId): Loan
    {
        $application = LoanApplication::findOrFail($applicationId);

        if ($application->status !== 'submitted' && $application->status !== 'under_review') {
            throw ValidationException::withMessages([
                'status' => 'Application must be submitted or under review to be approved'
            ]);
        }

        $product = $application->loanProduct;

        return DB::transaction(function () use ($application, $product) {
            // Calculate interest based on product
            $interest = $this->calculateInterest(
                $application->requested_amount,
                $product->interest_rate,
                $application->requested_tenure,
                $product->interest_type
            );

            $totalRepayment = $application->requested_amount + $interest;
            $monthlyInstallment = $totalRepayment / $application->requested_tenure;

            // Create the loan
            $loan = Loan::create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'loan_application_id' => $application->id,
                'customer_id' => $application->customer_id,
                'account_id' => $application->account_id,
                'loan_no' => Loan::generateLoanNumber(),
                'principal' => $application->requested_amount,
                'interest_rate' => $product->interest_rate,
                'tenure_months' => $application->requested_tenure,
                'total_interest' => $interest,
                'total_repayment' => $totalRepayment,
                'disbursed_amount' => 0,
                'outstanding_principal' => $application->requested_amount,
                'outstanding_interest' => $interest,
                'outstanding_total' => $totalRepayment,
                'status' => 'pending',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // Generate repayment schedule
            $this->generateSchedule($loan, $monthlyInstallment);

            // Update application status
            $application->update([
                'status' => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            return $loan->load('schedules');
        });
    }

    /**
     * Reject a loan application
     */
    public function rejectApplication(string $applicationId, string $reason): LoanApplication
    {
        $application = LoanApplication::findOrFail($applicationId);

        if (!in_array($application->status, ['submitted', 'under_review'])) {
            throw ValidationException::withMessages([
                'status' => 'Application must be submitted or under review to be rejected'
            ]);
        }

        $application->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return $application->fresh();
    }

    /**
     * Disburse a loan
     */
    public function disburseLoan(string $loanId, ?string $disbursementAccountId = null): Loan
    {
        $loan = Loan::with('application.loanProduct')->findOrFail($loanId);

        if ($loan->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending loans can be disbursed'
            ]);
        }

        $product = $loan->application->loanProduct;
        $disbursementAccount = $disbursementAccountId 
            ? Account::findOrFail($disbursementAccountId) 
            : ($loan->account_id ? Account::findOrFail($loan->account_id) : null);

        return DB::transaction(function () use ($loan, $product, $disbursementAccount) {
            // Calculate fees
            $processingFee = $product->calculateProcessingFee($loan->principal);
            $netDisbursement = $loan->principal - $processingFee;

            // Update loan status
            $loan->update([
                'disbursed_amount' => $loan->principal,
                'status' => 'active',
                'disbursed_by' => Auth::id(),
                'disbursed_at' => now()->toDateString(),
                'maturity_date' => now()->addMonths($loan->tenure_months)->toDateString(),
                'first_payment_date' => now()->addMonth()->toDateString(),
            ]);

            // Credit to disbursement account (if exists)
            if ($disbursementAccount && $disbursementAccount->status === 'active') {
                $this->accountService->credit(
                    $disbursementAccount->id,
                    $netDisbursement,
                    "Loan disbursement - {$loan->loan_no}",
                    'loan',
                    $loan->id
                );
            }

            // Create ledger entries for the loan
            $this->createLoanLedgerEntries($loan, $processingFee);

            // Update application status
            $loan->application->update([
                'status' => 'disbursed',
            ]);

            return $loan->fresh(['schedules', 'account']);
        });
    }

    /**
     * Record a loan repayment
     */
public function recordRepayment(
        string $loanId,
        float $amount,
        ?string $accountId = null,
        ?string $paymentChannel = null,
        ?string $reference = null
    ): \App\Models\LoanRepayment {
        $loan = Loan::findOrFail($loanId);

        if ($loan->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'Loan must be active for repayment'
            ]);
        }

        return DB::transaction(function () use ($loan, $amount, $accountId, $paymentChannel, $reference) {
            // Debit from account if provided
            if ($accountId) {
                $this->accountService->debit(
                    $accountId,
                    $amount,
                    "Loan repayment - {$loan->loan_no}",
                    'loan_repayment',
                    $loan->id
                );
            }

            // Create repayment record
            $repayment = \App\Models\LoanRepayment::create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'loan_id' => $loan->id,
                'account_id' => $accountId,
                'amount' => $amount,
                'payment_channel' => $paymentChannel,
                'reference' => $reference,
                'paid_at' => now(),
            ]);

            return $repayment;
        });
    }

    /**
     * Allocate repayment to loan schedules
     */
    public function allocateRepayment(string $repaymentId): Loan
    {
        $repayment = \App\Models\LoanRepayment::with('loan.schedules')->findOrFail($repaymentId);
        $loan = $repayment->loan;

        return DB::transaction(function () use ($loan, $repayment) {
            $remainingAmount = $repayment->amount;
            $schedules = $loan->schedules()
                ->where('status', '!=', 'paid')
                ->orderBy('due_date')
                ->get();

            foreach ($schedules as $schedule) {
                if ($remainingAmount <= 0) break;

                $scheduleDue = $schedule->total_due - $schedule->amount_paid;
                $allocation = min($remainingAmount, $scheduleDue);

                // Allocate: Penalty -> Fees -> Interest -> Principal
                $allocation = $this->allocateToSchedule($schedule, $allocation);

                $remainingAmount -= $allocation;
            }

            // Update loan outstanding amounts
            $this->updateLoanOutstanding($loan);

            // Mark repayment as allocated
            $repayment->update([
                'allocated' => true,
                'allocated_by' => Auth::id(),
                'allocated_at' => now(),
            ]);

            // Check if loan is fully paid
            if ($loan->outstanding_total <= 0) {
                $loan->update(['status' => 'closed']);
            }

            return $loan->fresh(['schedules', 'repayments']);
        });
    }

    /**
     * Get loan schedule
     */
    public function getSchedule(string $loanId): array
    {
        $loan = Loan::with('schedules')->findOrFail($loanId);

        return [
            'loan' => [
                'id' => $loan->id,
                'loan_no' => $loan->loan_no,
                'principal' => $loan->principal,
                'total_repayment' => $loan->total_repayment,
                'monthly_installment' => $loan->monthly_installment,
                'tenure_months' => $loan->tenure_months,
                'status' => $loan->status,
            ],
            'schedule' => $loan->schedules->map(function ($s) {
                return [
                    'installment_no' => $s->installment_no,
                    'due_date' => $s->due_date,
                    'principal_due' => $s->principal_due,
                    'interest_due' => $s->interest_due,
                    'fees_due' => $s->fees_due,
                    'penalty_due' => $s->penalty_due,
                    'total_due' => $s->total_due,
                    'amount_paid' => $s->amount_paid,
                    'status' => $s->status,
                ];
            }),
        ];
    }

    /**
     * Restructure a loan
     */
    public function restructureLoan(string $loanId, int $newTenure): Loan
    {
        $loan = Loan::findOrFail($loanId);

        if ($loan->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'Only active loans can be restructured'
            ]);
        }

        return DB::transaction(function () use ($loan, $newTenure) {
            // Recalculate interest
            $interest = $this->calculateInterest(
                $loan->outstanding_principal,
                $loan->interest_rate,
                $newTenure,
                'reducing'
            );

            $newTotalRepayment = $loan->outstanding_principal + $loan->outstanding_interest;
            $newMonthlyInstallment = $newTotalRepayment / $newTenure;

            // Update loan
            $loan->update([
                'tenure_months' => $newTenure,
                'total_interest' => $interest,
                'total_repayment' => $newTotalRepayment,
                'outstanding_interest' => $interest,
                'outstanding_total' => $newTotalRepayment,
                'maturity_date' => now()->addMonths($newTenure)->toDateString(),
            ]);

            // Regenerate schedule
            $loan->schedules()->delete();
            $this->generateSchedule($loan, $newMonthlyInstallment);

            return $loan->fresh(['schedules']);
        });
    }

    /**
     * Write off a loan
     */
    public function writeOffLoan(string $loanId, string $reason): Loan
    {
        $loan = Loan::findOrFail($loanId);

        $loan->update([
            'status' => 'writeoff',
        ]);

        return $loan;
    }

    /**
     * Calculate interest
     */
    protected function calculateInterest(float $principal, float $rate, int $tenure, string $type): float
    {
        if ($type === 'flat') {
            // Simple interest: Principal * Rate * (Tenure/12)
            return round($principal * ($rate / 100) * ($tenure / 12), 2);
        } else {
            // Reducing balance: Use EMI formula approximation
            $monthlyRate = $rate / 100 / 12;
            $emi = ($principal * $monthlyRate * pow(1 + $monthlyRate, $tenure)) / 
                   (pow(1 + $monthlyRate, $tenure) - 1);
            $totalRepayment = $emi * $tenure;
            return round($totalRepayment - $principal, 2);
        }
    }

    /**
     * Generate repayment schedule
     */
    protected function generateSchedule(Loan $loan, float $monthlyInstallment): void
    {
        $principalPerInstallment = $loan->principal / $loan->tenure_months;
        $interestPerInstallment = $loan->total_interest / $loan->tenure_months;

        for ($i = 1; $i <= $loan->tenure_months; $i++) {
            LoanSchedule::create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'loan_id' => $loan->id,
                'installment_no' => $i,
                'due_date' => now()->addMonths($i)->toDateString(),
                'principal_due' => $principalPerInstallment,
                'interest_due' => $interestPerInstallment,
                'fees_due' => 0,
                'penalty_due' => 0,
                'total_due' => $principalPerInstallment + $interestPerInstallment,
                'amount_paid' => 0,
                'principal_paid' => 0,
                'interest_paid' => 0,
                'fees_paid' => 0,
                'penalty_paid' => 0,
                'status' => 'unpaid',
            ]);
        }
    }

    /**
     * Create ledger entries for loan
     */
    protected function createLoanLedgerEntries(Loan $loan, float $processingFee): void
    {
        // Debit: Loan Receivable
        // Credit: Interest Income (accrued)
        // Credit: Processing Fee Income
        
        $loanReceivableAccount = \App\Models\LedgerAccount::where('code', '1110')->first();
        $interestIncomeAccount = \App\Models\LedgerAccount::where('code', '4010')->first();
        $feeIncomeAccount = \App\Models\LedgerAccount::where('code', '4110')->first();
        $cashAccount = \App\Models\LedgerAccount::where('code', '1010')->first();

        $lines = [
            [
                'ledger_account_id' => $loanReceivableAccount->id,
                'debit' => $loan->principal,
                'credit' => 0,
                'narration' => "Loan disbursement - {$loan->loan_no}",
            ],
            [
                'ledger_account_id' => $cashAccount->id,
                'debit' => 0,
                'credit' => $loan->principal - $processingFee,
                'narration' => "Cash disbursed - {$loan->loan_no}",
            ],
            [
                'ledger_account_id' => $feeIncomeAccount->id,
                'debit' => 0,
                'credit' => $processingFee,
                'narration' => "Processing fee - {$loan->loan_no}",
            ],
        ];

        $this->ledgerService->createJournalEntry(
            $lines,
            "Loan disbursement - {$loan->loan_no}",
            'loan',
            $loan->id
        );
    }

    /**
     * Allocate payment to schedule
     */
    protected function allocateToSchedule(LoanSchedule $schedule, float $amount): float
    {
        $allocated = 0;
        $remaining = $amount;

        // 1. Penalty
        $penaltyDue = $schedule->penalty_due - $schedule->penalty_paid;
        if ($penaltyDue > 0 && $remaining > 0) {
            $alloc = min($remaining, $penaltyDue);
            $schedule->update([
                'penalty_paid' => $schedule->penalty_paid + $alloc,
            ]);
            $remaining -= $alloc;
            $allocated += $alloc;
        }

        // 2. Fees
        $feesDue = $schedule->fees_due - $schedule->fees_paid;
        if ($feesDue > 0 && $remaining > 0) {
            $alloc = min($remaining, $feesDue);
            $schedule->update([
                'fees_paid' => $schedule->fees_paid + $alloc,
            ]);
            $remaining -= $alloc;
            $allocated += $alloc;
        }

        // 3. Interest
        $interestDue = $schedule->interest_due - $schedule->interest_paid;
        if ($interestDue > 0 && $remaining > 0) {
            $alloc = min($remaining, $interestDue);
            $schedule->update([
                'interest_paid' => $schedule->interest_paid + $alloc,
            ]);
            $remaining -= $alloc;
            $allocated += $alloc;
        }

        // 4. Principal
        $principalDue = $schedule->principal_due - $schedule->principal_paid;
        if ($principalDue > 0 && $remaining > 0) {
            $alloc = min($remaining, $principalDue);
            $schedule->update([
                'principal_paid' => $schedule->principal_paid + $alloc,
            ]);
            $remaining -= $alloc;
            $allocated += $alloc;
        }

        // Update schedule status
        $totalPaid = $schedule->principal_paid + $schedule->interest_paid + 
                     $schedule->fees_paid + $schedule->penalty_paid;
        
        $newStatus = 'unpaid';
        if ($totalPaid >= $schedule->total_due) {
            $newStatus = 'paid';
        } elseif ($totalPaid > 0) {
            $newStatus = 'partial';
        }

        $schedule->update([
            'amount_paid' => $totalPaid,
            'status' => $newStatus,
            'paid_at' => $newStatus === 'paid' ? now()->toDateString() : null,
        ]);

        return $allocated;
    }

    /**
     * Update loan outstanding amounts
     */
    protected function updateLoanOutstanding(Loan $loan): void
    {
        $schedules = $loan->schedules()->where('status', '!=', 'paid')->get();
        
        $outstandingPrincipal = $schedules->sum('principal_due') - $schedules->sum('principal_paid');
        $outstandingInterest = $schedules->sum('interest_due') - $schedules->sum('interest_paid');
        $outstandingTotal = $outstandingPrincipal + $outstandingInterest;

        $loan->update([
            'outstanding_principal' => $outstandingPrincipal,
            'outstanding_interest' => $outstandingInterest,
            'outstanding_total' => $outstandingTotal,
        ]);

        // Check for default
        if ($loan->isInDefault()) {
            $loan->update(['status' => 'defaulted']);
        }
    }
}