<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LedgerAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = $this->getChartOfAccounts();
        
        foreach ($accounts as $account) {
            LedgerAccount::updateOrCreate(
                ['code' => $account['code']],
                $account
            );
        }
    }

    protected function getChartOfAccounts(): array
    {
        return [
            // === ASSETS ===
            // Cash & Bank
            ['id' => (string) Str::uuid(), 'code' => '1000', 'name' => 'Cash and Cash Equivalents', 'type' => 'asset', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '1010', 'name' => 'Cash at Bank', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '1020', 'name' => 'Petty Cash', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            
            // Customer Accounts
            ['id' => (string) Str::uuid(), 'code' => '1100', 'name' => 'Customer Receivables', 'type' => 'asset', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '1110', 'name' => 'Loan Receivables', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '1120', 'name' => 'Interest Receivable', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            
            // Other Assets
            ['id' => (string) Str::uuid(), 'code' => '1200', 'name' => 'Fixed Assets', 'type' => 'asset', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '1300', 'name' => 'Intangible Assets', 'type' => 'asset', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '1400', 'name' => 'Prepayments', 'type' => 'asset', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],

            // === LIABILITIES ===
            // Customer Deposits
            ['id' => (string) Str::uuid(), 'code' => '2000', 'name' => 'Customer Deposits', 'type' => 'liability', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '2010', 'name' => 'Savings Deposits', 'type' => 'liability', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '2020', 'name' => 'Wallet Deposits', 'type' => 'liability', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            
            // Loans Payable
            ['id' => (string) Str::uuid(), 'code' => '2100', 'name' => 'Loans Payable', 'type' => 'liability', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            
            // Other Liabilities
            ['id' => (string) Str::uuid(), 'code' => '2200', 'name' => 'Accrued Expenses', 'type' => 'liability', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '2300', 'name' => 'Provisions', 'type' => 'liability', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '2400', 'name' => 'Deferred Revenue', 'type' => 'liability', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],

            // === EQUITY ===
            ['id' => (string) Str::uuid(), 'code' => '3000', 'name' => 'Capital', 'type' => 'equity', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '3010', 'name' => 'Share Capital', 'type' => 'equity', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '3020', 'name' => 'Retained Earnings', 'type' => 'equity', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '3100', 'name' => 'Reserves', 'type' => 'equity', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],

            // === INCOME ===
            ['id' => (string) Str::uuid(), 'code' => '4000', 'name' => 'Loan Interest Income', 'type' => 'income', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '4010', 'name' => 'Interest on Loans', 'type' => 'income', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '4020', 'name' => 'Interest on Overdraft', 'type' => 'income', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            
            ['id' => (string) Str::uuid(), 'code' => '4100', 'name' => 'Fee Income', 'type' => 'income', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '4110', 'name' => 'Processing Fees', 'type' => 'income', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '4120', 'name' => 'Late Payment Fees', 'type' => 'income', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '4130', 'name' => 'Administrative Fees', 'type' => 'income', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            
            ['id' => (string) Str::uuid(), 'code' => '4200', 'name' => 'Other Income', 'type' => 'income', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],

            // === EXPENSES ===
            ['id' => (string) Str::uuid(), 'code' => '5000', 'name' => 'Loan Interest Expense', 'type' => 'expense', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            
            ['id' => (string) Str::uuid(), 'code' => '5100', 'name' => 'Provision for Loan Losses', 'type' => 'expense', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '5110', 'name' => 'Bad Debt Expense', 'type' => 'expense', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            
            ['id' => (string) Str::uuid(), 'code' => '5200', 'name' => 'Operating Expenses', 'type' => 'expense', 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '5210', 'name' => 'Salaries and Wages', 'type' => 'expense', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '5220', 'name' => 'Rent and Utilities', 'type' => 'expense', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '5230', 'name' => 'Marketing and Advertising', 'type' => 'expense', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '5240', 'name' => 'Technology and Software', 'type' => 'expense', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '5250', 'name' => 'Professional Fees', 'type' => 'expense', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
            ['id' => (string) Str::uuid(), 'code' => '5290', 'name' => 'General Administrative Expenses', 'type' => 'expense', 'parent_id' => null, 'level' => 1, 'active' => true, 'allow_manual_entry' => true],
        ];
    }
}