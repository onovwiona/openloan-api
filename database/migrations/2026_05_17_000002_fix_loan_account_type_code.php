<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize legacy loan account codes to the new `LOAN` code
        DB::table('account_types')->whereIn('code', ['LOANREP', 'LOAN_REPAY'])->update([
            'code' => 'LOAN',
            'name' => 'Loan Liability Account',
            'description' => 'Liability account used for loan disbursement and repayment tracking. Not withdrawable or transferable by customers.',
            'account_category' => 'LOAN',
            'normal_balance' => 'CREDIT',
            'is_customer_visible' => true,
            'supports_deposit' => false,
            'supports_withdrawal' => false,
            'supports_transfer' => false,
            'requires_kyc' => true,
        ]);
    }

    public function down(): void
    {
        // Revert back to legacy code if rolling back
        DB::table('account_types')->where('code', 'LOAN')->update([
            'code' => 'LOANREP',
            'name' => 'Loan Repayment Account',
            'description' => 'Required for loan products to track loan transactions, ledger entries, balance audits etc',
        ]);
    }
};
