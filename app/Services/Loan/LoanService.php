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
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;



class LoanService
{
    public function __construct(
        protected AccountService $accountService,
        protected LedgerService $ledgerService
    ) {}

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
        return $product->requires_employment_profile
            || $this->isGovtLoanProduct($product)
            || $this->isSalaryLoanProduct($product);
    }

    /**
     * Ensure refinance is allowed for a loan's product
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureRefinanceAllowed(Loan $loan): void
    {
        $product = $loan->application->loanProduct;
        if (!($product->allow_refinance ?? false)) {
            throw ValidationException::withMessages(['refinance' => 'Refinance is not allowed for this loan product']);
        }
    }

    /**
     * Ensure topup is allowed for a loan's product
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureTopupAllowed(Loan $loan): void
    {
        $product = $loan->application->loanProduct;
        if (!($product->allow_topup ?? false)) {
            throw ValidationException::withMessages(['topup' => 'Topup is not allowed for this loan product']);
        }
    }

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
        ?float $payrollGross = null,
        ?float $payrollNet = null,
        ?string $employmentStatus = null,
        ?string $employerIdNumber = null,
        ?string $repaymentPlan = null
    ): LoanApplication {
        $product = LoanProduct::findOrFail($productId);
        // For government loan products repayment plan is optional (defaults to monthly).
        // For other products repayment_plan must be provided by the caller.
        if ($this->isGovtLoanProduct($product)) {
            $repaymentPlan = $repaymentPlan ?: 'monthly';
        } else {
            if (empty($repaymentPlan)) {
                throw ValidationException::withMessages([
                    'repayment_plan' => ['Repayment plan is required for this loan product.']
                ]);
            }
        }

        if ($repaymentPlan && !in_array($repaymentPlan, ['monthly', 'weekly', 'quarterly', 'annually'], true)) {
            throw ValidationException::withMessages([
                'repayment_plan' => ['A valid repayment plan is required for this loan product.']
            ]);
        }

        $activeApplicationQuery = LoanApplication::where('customer_id', $customerId)
            ->whereIn('status', ['draft', 'submitted', 'under_review', 'verified', 'approved', 'disbursed'])
            ->where(function ($query) {
                $query->whereDoesntHave('loan')
                    ->orWhereHas('loan', function ($loanQuery) {
                        $loanQuery->where('status', '!=', 'closed');
                    });
            });

        if ($accountId) {
            $activeAccountApplication = (clone $activeApplicationQuery)
                ->where('account_id', $accountId)
                ->first();

            if ($activeAccountApplication) {
                if ($activeAccountApplication->loan_product_id !== $product->id) {
                    throw ValidationException::withMessages([
                        'account_id' => ['This account is already used for a different loan application.']
                    ]);
                }

                throw ValidationException::withMessages([
                    'account_id' => ['This account is already used for an existing loan application for this product.']
                ]);
            }
        }

        $sameProductApplication = (clone $activeApplicationQuery)
            ->where('loan_product_id', $product->id)
            ->when($accountId, fn($query) => $query->where('account_id', '!=', $accountId))
            ->exists();

        if ($sameProductApplication) {
            throw ValidationException::withMessages([
                'loan_product_id' => ['You already have an active loan application for this loan product.']
            ]);
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

        return DB::transaction(function () use ($customerId, $product, $amount, $tenure, $accountId, $purpose, $monthlyIncome, $payrollGross, $payrollNet, $employmentStatus, $employerIdNumber, $repaymentPlan) {
            $createData = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'customer_id' => $customerId,
                'loan_product_id' => $product->id,
                'account_id' => $accountId,
                'application_no' => LoanApplication::generateApplicationNumber(),
                'requested_amount' => $amount,
                'requested_tenure' => $tenure,
                'repayment_plan' => $repaymentPlan,
                'monthly_income' => $monthlyIncome,
                'payroll_gross' => $payrollGross,
                'payroll_net' => $payrollNet,
                'employment_status' => $employmentStatus,
                'purpose' => $purpose,
                'status' => 'draft',
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('loan_applications', 'employer_id_number')) {
                $createData['employer_id_number'] = $employerIdNumber;
            }

            $application = LoanApplication::create($createData);

            // Auto create LOAN account if not provided
            if (!$accountId) {
                $repayType = \App\Models\AccountType::where('code', 'LOAN')->firstOrFail();
                $repayAccount = $this->accountService->createAccount($customerId, $repayType->id, 'Loan - ' . $application->application_no);
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
        $repaymentPlan = $application->repayment_plan ?: 'monthly';

        if (!in_array($repaymentPlan, ['monthly', 'weekly', 'quarterly', 'annually'], true)) {
            throw ValidationException::withMessages([
                'repayment_plan' => 'A valid repayment plan is required to approve this application.'
            ]);
        }

        return DB::transaction(function () use ($application, $product, $repaymentPlan) {
            // Calculate interest based on product
            $interest = $this->calculateInterest(
                $application->requested_amount,
                $product->interest_rate,
                $application->requested_tenure,
                $product->interest_type
            );

            $totalRepayment = $application->requested_amount + $interest;

            $loan = Loan::create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'loan_application_id' => $application->id,
                'customer_id' => $application->customer_id,
                'account_id' => $application->account_id,
                'loan_no' => Loan::generateLoanNumber(),
                'principal' => $application->requested_amount,
                'interest_rate' => $product->interest_rate,
                'tenure_months' => $application->requested_tenure,
                'repayment_plan' => $repaymentPlan,
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

            $application->update([
                'status' => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            return $loan;
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
     * Disburse a loan.
     *
     * Funds the customer's wallet with net disbursement amount, credits LOAN account with loan principal
     * to track outstanding loan balance, and creates ledger entries for audit purposes.
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
        $walletAccount = $this->getDefaultWalletAccountForCustomer($loan->customer_id);
        $loanRepayAccount = $this->resolveLoanRepaymentAccount($loan);
        $fundingAccount = $disbursementAccountId ? Account::findOrFail($disbursementAccountId) : null;

        return DB::transaction(function () use ($loan, $product, $walletAccount, $loanRepayAccount, $fundingAccount) {
            $processingFee = $product->calculateProcessingFee($loan->principal);
            $netDisbursement = $loan->principal - $processingFee;
            $firstPaymentDate = $this->getFirstPaymentDate($loan);
            $installmentCount = $this->getInstallmentCount($loan);

            $loan->update([
                'disbursed_amount' => $loan->principal,
                'status' => 'active',
                'disbursed_by' => Auth::id(),
                'disbursed_at' => now()->toDateString(),
                'maturity_date' => $this->getMaturityDate($loan),
                'first_payment_date' => $firstPaymentDate->toDateString(),
            ]);

            if ($fundingAccount && $fundingAccount->id !== $walletAccount->id) {
                $this->accountService->debit(
                    $fundingAccount->id,
                    $netDisbursement,
                    "Loan funds moved to wallet - {$loan->loan_no}",
                    'loan_disbursement',
                    $loan->id
                );

                $this->accountService->credit(
                    $walletAccount->id,
                    $netDisbursement,
                    "Loan disbursement - {$loan->loan_no}",
                    'loan_disbursement',
                    $loan->id
                );
            } else {
                $this->accountService->credit(
                    $walletAccount->id,
                    $netDisbursement,
                    "Loan disbursement - {$loan->loan_no}",
                    'loan_disbursement',
                    $loan->id
                );
            }

            $this->accountService->credit(
                $loanRepayAccount->id,
                $loan->principal,
                "Loan disbursement - {$loan->loan_no}",
                'loan_disbursement',
                $loan->id,
                true
            );

            $this->createLoanLedgerEntries($loan, $processingFee, $netDisbursement);

            $loan->application->update(['status' => 'disbursed']);

            $loan->schedules()->delete();
            $this->generateSchedule($loan, $firstPaymentDate, $installmentCount);

            return $loan->fresh(['schedules', 'account']);
        });
    }

    /**
     * Resolve the customer's default wallet account for repayment
     */
    protected function getDefaultWalletAccountForCustomer(string $customerId): Account
    {
        $walletAccounts = Account::with('balance')
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->whereHas('accountType', function ($query) {
                $query->where('code', 'WAL');
            })
            ->get();

        if ($walletAccounts->isEmpty()) {
            throw ValidationException::withMessages([
                'account_id' => 'No active default wallet account found for customer.'
            ]);
        }

        return $walletAccounts->sortByDesc(fn ($account) => $account->balance?->available_balance ?? 0)->first();
    }

    protected function resolveLoanRepaymentAccount(Loan $loan): Account
    {
        $repayAccount = null;

        if ($loan->account_id) {
            $repayAccount = Account::find($loan->account_id);
        }

        if (! $repayAccount && $loan->application?->account_id) {
            $repayAccount = Account::find($loan->application->account_id);
        }

        if (! $repayAccount) {
            $repayType = AccountType::where('code', 'LOAN')->firstOrFail();
            $repayAccount = $this->accountService->createAccount($loan->customer_id, $repayType->id, 'Loan - ' . $loan->loan_no);
            $loan->update(['account_id' => $repayAccount->id]);
        }

        if ($repayAccount->status !== 'active') {
            throw ValidationException::withMessages([
                'account_id' => 'Loan repayment account is not active.'
            ]);
        }

        return $repayAccount;
    }

    /**
     * Record a loan repayment
     */
    public function recordRepayment(
        string $loanId,
        float $amount,
        ?string $accountId = null,
        ?string $paymentChannel = null,
        ?string $reference = null,
        bool $directToRepayAccount = false
    ): \App\Models\LoanRepayment {
        $loan = Loan::findOrFail($loanId);

        if ($loan->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'Loan must be active for repayment'
            ]);
        }

        $loanRepayAccount = $this->resolveLoanRepaymentAccount($loan);

        if (! $accountId) {
            if ($directToRepayAccount) {
                $accountId = $loanRepayAccount->id;
            } else {
                $defaultWallet = $this->getDefaultWalletAccountForCustomer($loan->customer_id);
                $accountId = $defaultWallet->id;
            }
        }

        $paymentChannel = $paymentChannel ?: $this->inferPaymentChannel($accountId);

        return DB::transaction(function () use ($loan, $amount, $accountId, $paymentChannel, $reference, $loanRepayAccount, $directToRepayAccount) {
            $sourceAccount = Account::find($accountId);
            $isLoanRepaySource = $sourceAccount && $sourceAccount->accountType?->code === 'LOAN';

            if ($directToRepayAccount || $isLoanRepaySource || $accountId === $loanRepayAccount->id) {
                $this->accountService->debit(
                    $loanRepayAccount->id,
                    $amount,
                    "Loan repayment received - {$loan->loan_no}",
                    'loan_repayment',
                    $loan->id,
                    true
                );
            } else {
                $this->accountService->moveFunds(
                    $accountId,
                    $loanRepayAccount->id,
                    $amount,
                    "Loan repayment - {$loan->loan_no}",
                    'loan_repayment',
                    $loan->id
                );
            }

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
     * Pay off the remaining balance of an active loan
     */
    public function payoffLoan(
        string $loanId,
        ?string $accountId = null,
        ?string $paymentChannel = null,
        ?string $reference = null
    ): Loan {
        $loan = Loan::findOrFail($loanId);

        if ($loan->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'Only active loans can be paid off'
            ]);
        }

        if ($loan->outstanding_total <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Loan is already fully paid'
            ]);
        }

        $amount = $this->calculatePayoffAmount($loanId);

        $repayment = $this->recordRepayment($loanId, $amount, $accountId, $paymentChannel, $reference);
        $allocatedLoan = $this->allocateRepayment($repayment->id);

        // Check if loan is fully paid after allocation
        if ($allocatedLoan->outstanding_total <= 0) {
            $allocatedLoan->status = 'closed';
            $allocatedLoan->closed_at = now();
            $allocatedLoan->save();
        }

        return $allocatedLoan;
    }

    /**
     * Infer payment channel from account type
     */
    protected function inferPaymentChannel(string $accountId): string
    {
        $account = Account::with('accountType')->find($accountId);
        if ($account && $account->accountType && $account->accountType->code === 'WAL') {
            return 'wallet';
        }

        return 'bank';
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

            $totals = [
                'penalty' => 0,
                'fees' => 0,
                'interest' => 0,
                'principal' => 0,
            ];

            foreach ($schedules as $schedule) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $scheduleDue = $schedule->total_due - $schedule->amount_paid;
                $allocation = min($remainingAmount, $scheduleDue);

                $breakdown = $this->allocateToSchedule($schedule, $allocation);
                $remainingAmount -= $breakdown['allocated'];

                $totals['penalty'] += $breakdown['penalty'];
                $totals['fees'] += $breakdown['fees'];
                $totals['interest'] += $breakdown['interest'];
                $totals['principal'] += $breakdown['principal'];

                \App\Models\LoanRepaymentAllocation::create([
                    'repayment_id' => $repayment->id,
                    'schedule_id' => $schedule->id,
                    'principal_amount' => $breakdown['principal'],
                    'interest_amount' => $breakdown['interest'],
                    'fees_amount' => $breakdown['fees'],
                    'penalty_amount' => $breakdown['penalty'],
                    'status' => 'applied',
                ]);
            }

            $repayment->update([
                'principal_amount' => $totals['principal'],
                'interest_amount' => $totals['interest'],
                'fees_amount' => $totals['fees'],
                'penalty_amount' => $totals['penalty'],
                'allocated' => true,
                'allocated_by' => Auth::id(),
                'allocated_at' => now(),
            ]);

            $this->updateLoanOutstanding($loan);

            if ($loan->outstanding_total <= 0) {
                $loan->update(['status' => 'closed']);
            }

            return $loan->fresh(['schedules', 'repayments', 'application']);
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
                    'principal_outstanding' => $s->principal_outstanding,
                    'interest_outstanding' => $s->interest_outstanding,
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
            $firstPaymentDate = $this->getFirstPaymentDate($loan);
            $installmentCount = $this->getInstallmentCount($loan);
            $this->generateSchedule($loan, $firstPaymentDate, $installmentCount);

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
     * Create ledger entries for loan
     */
    protected function createLoanLedgerEntries(Loan $loan, float $processingFee, float $netDisbursement): void
    {
        $loanReceivableAccount = $this->getOrCreateLedgerAccount('1110', 'Loan Receivables', 'asset');
        $feeIncomeAccount = $this->getOrCreateLedgerAccount('4110', 'Processing Fees', 'income');
        $cashAccount = $this->getOrCreateLedgerAccount('1010', 'Cash at Bank', 'asset');

        $lines = [
            [
                'ledger_account_id' => $loanReceivableAccount->id,
                'debit' => $loan->principal,
                'credit' => 0,
                'narration' => "Loan receivable created - {$loan->loan_no}",
            ],
            [
                'ledger_account_id' => $cashAccount->id,
                'debit' => 0,
                'credit' => $netDisbursement,
                'narration' => "Wallet funding applied - {$loan->loan_no}",
            ],
        ];

        if ($processingFee > 0) {
            $lines[] = [
                'ledger_account_id' => $feeIncomeAccount->id,
                'debit' => 0,
                'credit' => $processingFee,
                'narration' => "Processing fee - {$loan->loan_no}",
            ];
        }

        $this->ledgerService->createJournalEntry(
            $lines,
            "Loan disbursement - {$loan->loan_no}",
            'loan',
            $loan->id
        );
    }

    protected function getOrCreateLedgerAccount(string $code, string $name, string $type): \App\Models\LedgerAccount
    {
        return \App\Models\LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'type' => $type,
                'level' => 1,
                'active' => true,
                'allow_manual_entry' => true,
            ]
        );
    }

    protected function getFirstPaymentDate(Loan $loan): Carbon
    {
        $firstPayment = now();

        if ($loan->repayment_plan === 'weekly') {
            $firstPayment = now()->addWeek();
        } elseif ($loan->repayment_plan === 'quarterly') {
            $firstPayment = now()->addMonths(3);
        } elseif ($loan->repayment_plan === 'annually') {
            $firstPayment = now()->addYear();
        } else {
            $firstPayment = now()->addMonth();
        }

        return $firstPayment;
    }

    protected function getMaturityDate(Loan $loan): string
    {
        if ($loan->repayment_plan === 'weekly') {
            return now()->addWeeks($loan->tenure_months * 4)->toDateString();
        }

        if ($loan->repayment_plan === 'quarterly') {
            return now()->addMonths($loan->tenure_months + 2)->toDateString();
        }

        if ($loan->repayment_plan === 'annually') {
            return now()->addYears((int) ceil($loan->tenure_months / 12))->toDateString();
        }

        return now()->addMonths($loan->tenure_months)->toDateString();
    }

    protected function getInstallmentCount(Loan $loan): int
    {
        return match ($loan->repayment_plan) {
            'weekly' => max(1, $loan->tenure_months * 4),
            'quarterly' => max(1, (int) ceil($loan->tenure_months / 3)),
            'annually' => max(1, (int) ceil($loan->tenure_months / 12)),
            default => max(1, $loan->tenure_months),
        };
    }

    protected function generateSchedule(Loan $loan, Carbon $startDate, int $installmentCount): void
    {
        $principalPerInstallment = round($loan->principal / $installmentCount, 2);
        $interestPerInstallment = round($loan->total_interest / $installmentCount, 2);
        $intervalMethod = 'addMonths';
        $intervalValue = 1;

        switch ($loan->repayment_plan) {
            case 'weekly':
                $intervalMethod = 'addWeeks';
                $intervalValue = 1;
                break;
            case 'quarterly':
                $intervalMethod = 'addMonths';
                $intervalValue = 3;
                break;
            case 'annually':
                $intervalMethod = 'addYears';
                $intervalValue = 1;
                break;
        }

        for ($i = 1; $i <= $installmentCount; $i++) {
            $dueDate = $startDate->copy();
            if ($i > 1) {
                $dueDate = $dueDate->{$intervalMethod}($intervalValue * ($i - 1));
            }

            LoanSchedule::create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'loan_id' => $loan->id,
                'installment_no' => $i,
                'due_date' => $dueDate->toDateString(),
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
     * Allocate payment to schedule
     */
    protected function allocateToSchedule(LoanSchedule $schedule, float $amount): array
    {
        $totals = [
            'penalty' => 0,
            'fees' => 0,
            'interest' => 0,
            'principal' => 0,
            'allocated' => 0,
        ];

        $remaining = $amount;

        // 1. Penalty
        $penaltyDue = $schedule->penalty_due - $schedule->penalty_paid;
        if ($penaltyDue > 0 && $remaining > 0) {
            $alloc = min($remaining, $penaltyDue);
            $schedule->update([
                'penalty_paid' => $schedule->penalty_paid + $alloc,
            ]);
            $remaining -= $alloc;
            $totals['penalty'] += $alloc;
            $totals['allocated'] += $alloc;
        }

        // 2. Fees
        $feesDue = $schedule->fees_due - $schedule->fees_paid;
        if ($feesDue > 0 && $remaining > 0) {
            $alloc = min($remaining, $feesDue);
            $schedule->update([
                'fees_paid' => $schedule->fees_paid + $alloc,
            ]);
            $remaining -= $alloc;
            $totals['fees'] += $alloc;
            $totals['allocated'] += $alloc;
        }

        // 3. Interest
        $interestDue = $schedule->interest_due - $schedule->interest_paid;
        if ($interestDue > 0 && $remaining > 0) {
            $alloc = min($remaining, $interestDue);
            $schedule->update([
                'interest_paid' => $schedule->interest_paid + $alloc,
            ]);
            $remaining -= $alloc;
            $totals['interest'] += $alloc;
            $totals['allocated'] += $alloc;
        }

        // 4. Principal
        $principalDue = $schedule->principal_due - $schedule->principal_paid;
        if ($principalDue > 0 && $remaining > 0) {
            $alloc = min($remaining, $principalDue);
            $schedule->update([
                'principal_paid' => $schedule->principal_paid + $alloc,
            ]);
            $remaining -= $alloc;
            $totals['principal'] += $alloc;
            $totals['allocated'] += $alloc;
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

        return $totals;
    }

    /**
     * Update loan outstanding amounts
     */
    protected function updateLoanOutstanding(Loan $loan): void
    {
        $schedules = $loan->schedules()->where('status', '!=', 'paid')->get();
        
        $outstandingPrincipal = $schedules->sum('principal_due') - $schedules->sum('principal_paid');
        $outstandingInterest = $schedules->sum('interest_due') - $schedules->sum('interest_paid');
        $outstandingFees = $schedules->sum('fees_due') - $schedules->sum('fees_paid');
        $outstandingPenalty = $schedules->sum('penalty_due') - $schedules->sum('penalty_paid');
        $outstandingTotal = $schedules->sum('total_due') - $schedules->sum('amount_paid');

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

    /**
     * Post ledger entries for a loan repayment
     */
    public function postRepaymentLedgerEntries(\App\Models\LoanRepayment $repayment): void
    {
        $loan = $repayment->loan;
        $amount = $repayment->amount;

        $loanRepayAccount = $this->resolveLoanRepaymentAccount($loan);
        $loanRepayLedgerAccount = $this->accountService->getOrCreateCustomerLedgerAccount($loanRepayAccount);
        $receivableAccount = $this->getOrCreateLedgerAccount('1110', 'Loan Receivables', 'asset');
        $interestIncomeAccount = $this->getOrCreateLedgerAccount('4010', 'Interest on Loans', 'income');
        $feeIncomeAccount = $this->getOrCreateLedgerAccount('4110', 'Processing Fees', 'income');
        $penaltyIncomeAccount = $this->getOrCreateLedgerAccount('4120', 'Late Payment Fees', 'income');

        $lines = [];
        $remainingAmount = $amount;

        $lines[] = [
            'ledger_account_id' => $loanRepayLedgerAccount->id,
            'debit' => $amount,
            'credit' => 0,
            'narration' => "Loan repayment settlement - {$loan->loan_no}",
        ];

        // Allocate across schedules: penalty → fees → interest → principal
        $schedules = $loan->schedules()->where('status', '!=', 'paid')->orderBy('installment_no')->get();

        foreach ($schedules as $schedule) {
            if ($remainingAmount <= 0) break;

            // 1. Penalty
            $penaltyDue = $schedule->penalty_due - $schedule->penalty_paid;
            if ($penaltyDue > 0 && $remainingAmount > 0) {
                $alloc = min($remainingAmount, $penaltyDue);
                $lines[] = [
                    'ledger_account_id' => $penaltyIncomeAccount->id,
                    'debit' => 0,
                    'credit' => $alloc,
                    'narration' => "Late payment fee collected - installment {$schedule->installment_no}",
                ];
                $remainingAmount -= $alloc;
            }

            // 2. Fees
            $feesDue = $schedule->fees_due - $schedule->fees_paid;
            if ($feesDue > 0 && $remainingAmount > 0) {
                $alloc = min($remainingAmount, $feesDue);
                $lines[] = [
                    'ledger_account_id' => $feeIncomeAccount->id,
                    'debit' => 0,
                    'credit' => $alloc,
                    'narration' => "Service fee collected - installment {$schedule->installment_no}",
                ];
                $remainingAmount -= $alloc;
            }

            // 3. Interest
            $interestDue = $schedule->interest_due - $schedule->interest_paid;
            if ($interestDue > 0 && $remainingAmount > 0) {
                $alloc = min($remainingAmount, $interestDue);
                $lines[] = [
                    'ledger_account_id' => $interestIncomeAccount->id,
                    'debit' => 0,
                    'credit' => $alloc,
                    'narration' => "Interest collected - installment {$schedule->installment_no}",
                ];
                $remainingAmount -= $alloc;
            }

            // 4. Principal
            $principalDue = $schedule->principal_due - $schedule->principal_paid;
            if ($principalDue > 0 && $remainingAmount > 0) {
                $alloc = min($remainingAmount, $principalDue);
                $lines[] = [
                    'ledger_account_id' => $receivableAccount->id,
                    'debit' => 0,
                    'credit' => $alloc,
                    'narration' => "Principal collected - installment {$schedule->installment_no}",
                ];
                $remainingAmount -= $alloc;
            }
        }

        if ($remainingAmount > 0) {
            $lines[] = [
                'ledger_account_id' => $receivableAccount->id,
                'debit' => 0,
                'credit' => $remainingAmount,
                'narration' => "Loan repayment overflow applied to receivable - {$loan->loan_no}",
            ];
            $remainingAmount = 0;
        }

        $this->ledgerService->createJournalEntry(
            $lines,
            "Loan repayment - {$loan->loan_no}",
            'loan_repayment',
            $loan->id
        );
    }

    /**
     * Calculate the payoff amount for a loan
     */
    public function calculatePayoffAmount(string $loanId): float
    {
        $loan = Loan::with('schedules')->findOrFail($loanId);

        if ($loan->status === 'closed') {
            return 0;
        }

        $totalOutstanding = $loan->outstanding_total;

        // Add any late payment penalties that might apply
        // This is a simplified calculation - you might want to add more complex logic
        $today = now()->toDateString();
        $overdueSchedules = $loan->schedules()
            ->where('due_date', '<', $today)
            ->whereRaw('(principal_due + interest_due + fees_due) > (principal_paid + interest_paid + fees_paid)')
            ->get();

        $penaltyAmount = 0;
        foreach ($overdueSchedules as $schedule) {
            $daysOverdue = now()->diffInDays(\Carbon\Carbon::parse($schedule->due_date));
            $penaltyAmount += ($schedule->principal_due + $schedule->interest_due + $schedule->fees_due -
                             $schedule->principal_paid - $schedule->interest_paid - $schedule->fees_paid) *
                             ($loan->application->loanProduct->penalty_rate / 100) * min($daysOverdue, 30);
        }

        return round($totalOutstanding + $penaltyAmount, 2);
    }

    /**
     * Get payoff quote for a loan
     */
    public function getPayoffQuote(string $loanId): array
    {
        $loan = Loan::with(['schedules', 'application.loanProduct'])->findOrFail($loanId);

        $payoffAmount = $this->calculatePayoffAmount($loanId);

        return [
            'loan_id' => $loan->id,
            'loan_no' => $loan->loan_no,
            'outstanding_principal' => $loan->outstanding_principal,
            'outstanding_interest' => $loan->outstanding_interest,
            'outstanding_fees' => $loan->outstanding_total - $loan->outstanding_principal - $loan->outstanding_interest,
            'payoff_amount' => $payoffAmount,
            'calculated_at' => now()->toISOString(),
            'valid_until' => now()->addHours(24)->toISOString(), // Quote valid for 24 hours
        ];
    }
}