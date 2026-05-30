<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clear existing dummy data
        DB::table('account_types')->truncate();
        DB::table('loan_products')->truncate();

        // Seed account types
        $accountTypes = [
            [
                'id' => Str::uuid(),
                'code' => 'WAL',
                'name' => 'Wallet Account',
                'description' => 'Account for normal transactions (transfer, withdraw, deposit, repay_loan debts, pay bills etc)',
                'currency' => 'NGN',
                'min_balance' => 0.00,
                'max_balance' => null,
                'allow_overdraft' => false,
                'overdraft_limit' => 0.00,
                'accrues_interest' => false,
                'interest_rate' => 0.00,
                'active' => true,
            ],
            [
                'id' => Str::uuid(),
                'code' => 'SAV',
                'name' => 'Savings Account',
                'description' => 'Savings account to hold money long term with ROI at end of term',
                'currency' => 'NGN',
                'min_balance' => 0.00,
                'max_balance' => null,
                'allow_overdraft' => false,
                'overdraft_limit' => 0.00,
                'accrues_interest' => true,
                'interest_rate' => 4.50,
                'active' => true,
            ],
            [
                'id' => Str::uuid(),
                'code' => 'COOP',
                'name' => 'Cooperative Account',
                'description' => 'For regular or irregular savings that attracts interest and can be used as a requirement to access cooperative loan product',
                'currency' => 'NGN',
                'min_balance' => 0.00,
                'max_balance' => null,
                'allow_overdraft' => false,
                'overdraft_limit' => 0.00,
                'accrues_interest' => true,
                'interest_rate' => 5.00,
                'active' => true,
            ],
            [
                'id' => Str::uuid(),
                'code' => 'LOAN',
                'name' => 'Loan Liability Account',
                'description' => 'Liability account used for loan disbursement and repayment tracking. Not withdrawable or transferable by customers.',
                'currency' => 'NGN',
                'min_balance' => 0.00,
                'max_balance' => null,
                'allow_overdraft' => false,
                'overdraft_limit' => 0.00,
                'accrues_interest' => false,
                'interest_rate' => 0.00,
                'active' => true,
            ],
            [
                'id' => Str::uuid(),
                'code' => 'ESC',
                'name' => 'Escrow Account',
                'description' => 'For escrow applications between parties',
                'currency' => 'NGN',
                'min_balance' => 0.00,
                'max_balance' => null,
                'allow_overdraft' => false,
                'overdraft_limit' => 0.00,
                'accrues_interest' => false,
                'interest_rate' => 0.00,
                'active' => true,
            ],
            [
                'id' => Str::uuid(),
                'code' => 'JOINT',
                'name' => 'Joint Account',
                'description' => 'For individuals, couples etc. Multiple signatories',
                'currency' => 'NGN',
                'min_balance' => 0.00,
                'max_balance' => null,
                'allow_overdraft' => false,
                'overdraft_limit' => 0.00,
                'accrues_interest' => false,
                'interest_rate' => 0.00,
                'active' => true,
            ],
            [
                'id' => Str::uuid(),
                'code' => 'SAL',
                'name' => 'Salary Account',
                'description' => 'For salary crediting and disbursement. Employer must be captured',
                'currency' => 'NGN',
                'min_balance' => 0.00,
                'max_balance' => null,
                'allow_overdraft' => false,
                'overdraft_limit' => 0.00,
                'accrues_interest' => false,
                'interest_rate' => 0.00,
                'active' => true,
            ],
        ];

        DB::table('account_types')->insert($accountTypes);

        // Get account type IDs for loan liability account
        $loanLiabilityAccountType = DB::table('account_types')->where('code', 'LOAN')->first();
        $loanLiabilityAccountTypeId = $loanLiabilityAccountType?->id;

        // Seed loan products
        $loanProducts = [
            [
                'id' => Str::uuid(),
                'code' => 'CSL',
                'name' => 'Civil Servant Loan',
                'description' => 'For government certified workers (local, state, or federal government staffs with valid and verifiable employer ID via government computer IDs. Requires KYC, documents, passport photo, and loan account)',
                'requires_account' => true,
                'repayment_account_type_id' => $loanLiabilityAccountTypeId,
                'min_amount' => 50000.00,
                'max_amount' => 5000000.00,
                'interest_type' => 'reducing',
                'interest_rate' => 12.00,
                'tenure_min_months' => 6,
                'tenure_max_months' => 60,
                'processing_fee' => 2.50,
                'penalty_rate' => 3.00,
                'insurance_fee' => 1.00,
                'legal_fee' => 0.50,
                'allow_early_repayment' => true,
                'early_repayment_penalty' => 0.00,
                'requires_guarantor' => false,
                'min_guarantors' => 0,
                'requires_collateral' => false,
                'requires_bank_statement' => false,
                'requires_proof_income' => true,
                'requires_passport' => true,
                'requires_employment_profile' => false,
                'required_employer_type' => null,
                'active' => true,
            ],
            [
                'id' => Str::uuid(),
                'code' => 'SME',
                'name' => 'SME Loan',
                'description' => 'For startups with cash flow to show high turnover. Requires KYC, documents, passport photo, statement of account from date range, guarantor notes and uploads. Should have cooperative account and loan account',
                'requires_account' => true,
                'repayment_account_type_id' => $loanLiabilityAccountTypeId,
                'min_amount' => 100000.00,
                'max_amount' => 10000000.00,
                'interest_type' => 'reducing',
                'interest_rate' => 14.00,
                'tenure_min_months' => 6,
                'tenure_max_months' => 48,
                'processing_fee' => 3.00,
                'penalty_rate' => 3.50,
                'insurance_fee' => 1.50,
                'legal_fee' => 1.00,
                'allow_early_repayment' => true,
                'early_repayment_penalty' => 0.00,
                'requires_guarantor' => true,
                'min_guarantors' => 1,
                'requires_collateral' => false,
                'requires_bank_statement' => true,
                'requires_proof_income' => true,
                'requires_passport' => true,
                'requires_employment_profile' => false,
                'required_employer_type' => null,
                'active' => true,
            ],
            [
                'id' => Str::uuid(),
                'code' => 'SPL',
                'name' => 'Special Loan',
                'description' => 'General purpose loan requiring all KYC, passport photo, 2 or more guarantors, and collateral documents. Requires loan account',
                'requires_account' => true,
                'repayment_account_type_id' => $loanLiabilityAccountTypeId,
                'min_amount' => 100000.00,
                'max_amount' => 50000000.00,
                'interest_type' => 'reducing',
                'interest_rate' => 15.00,
                'tenure_min_months' => 6,
                'tenure_max_months' => 84,
                'processing_fee' => 3.50,
                'penalty_rate' => 4.00,
                'insurance_fee' => 2.00,
                'legal_fee' => 1.50,
                'allow_early_repayment' => true,
                'early_repayment_penalty' => 0.00,
                'requires_guarantor' => true,
                'min_guarantors' => 2,
                'requires_collateral' => true,
                'requires_bank_statement' => false,
                'requires_proof_income' => true,
                'requires_passport' => true,
                'requires_employment_profile' => false,
                'required_employer_type' => null,
                'active' => true,
            ],
            [
                'id' => Str::uuid(),
                'code' => 'COOPL',
                'name' => 'Cooperative Loan',
                'description' => 'Must have cooperative account. Loan amount determined by savings amount. Requires passport photo. No extra documents unless loan amount exceeds threshold',
                'requires_account' => true,
                'repayment_account_type_id' => $loanLiabilityAccountTypeId,
                'min_amount' => 10000.00,
                'max_amount' => 5000000.00,
                'interest_type' => 'reducing',
                'interest_rate' => 10.00,
                'tenure_min_months' => 3,
                'tenure_max_months' => 60,
                'processing_fee' => 1.50,
                'penalty_rate' => 2.50,
                'insurance_fee' => 0.75,
                'legal_fee' => 0.25,
                'allow_early_repayment' => true,
                'early_repayment_penalty' => 0.00,
                'requires_guarantor' => false,
                'min_guarantors' => 0,
                'requires_collateral' => false,
                'requires_bank_statement' => false,
                'requires_proof_income' => false,
                'requires_passport' => true,
                'requires_employment_profile' => false,
                'required_employer_type' => null,
                'active' => true,
            ],
            [
                'id' => Str::uuid(),
                'code' => 'SAL',
                'name' => 'Salary Loan',
                'description' => 'For employed individuals. Must show employment documents and verification from employer',
                'requires_account' => true,
                'repayment_account_type_id' => $loanLiabilityAccountTypeId,
                'min_amount' => 50000.00,
                'max_amount' => 3000000.00,
                'interest_type' => 'reducing',
                'interest_rate' => 11.00,
                'tenure_min_months' => 3,
                'tenure_max_months' => 60,
                'processing_fee' => 2.00,
                'penalty_rate' => 3.00,
                'insurance_fee' => 0.50,
                'legal_fee' => 0.25,
                'allow_early_repayment' => true,
                'early_repayment_penalty' => 0.00,
                'requires_guarantor' => false,
                'min_guarantors' => 0,
                'requires_collateral' => false,
                'requires_bank_statement' => false,
                'requires_proof_income' => true,
                'requires_passport' => true,
                'requires_employment_profile' => true,
                'required_employer_type' => 'private',
                'active' => true,
            ],
        ];

        if (! Schema::hasColumn('loan_products', 'requires_employment_profile')) {
            foreach ($loanProducts as &$loanProduct) {
                unset($loanProduct['requires_employment_profile'], $loanProduct['required_employer_type']);
            }
            unset($loanProduct);
        }

        DB::table('loan_products')->insert($loanProducts);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('account_types')->truncate();
        DB::table('loan_products')->truncate();
    }
};
