<?php

namespace Database\Seeders;

use App\Models\AccountType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AccountTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'id' => (string) Str::uuid(),
                'code' => 'SAV',
                'name' => 'Savings Account',
                'currency' => 'NGN',
                'min_balance' => 0,
                'allow_overdraft' => false,
                'accrues_interest' => true,
                'interest_rate' => 2.5,
                'description' => 'Basic savings account for individuals',
                'active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'code' => 'WAL',
                'name' => 'Wallet Account',
                'currency' => 'NGN',
                'min_balance' => 0,
                'allow_overdraft' => false,
                'accrues_interest' => false,
                'interest_rate' => 0,
                'description' => 'Digital wallet for quick transactions',
                'active' => true,
            ],
[
                'id' => (string) Str::uuid(),
                'code' => 'LOAN_REPAY',
                'name' => 'Loan Repayment Account',
                'currency' => 'NGN',
                'min_balance' => 0,
                'allow_overdraft' => true,
                'overdraft_limit' => 100000,
                'accrues_interest' => false,
                'interest_rate' => 0,
                'description' => 'Dedicated account for loan repayments',
                'active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'code' => 'ESCROW',
                'name' => 'Escrow Account',
                'currency' => 'NGN',
                'min_balance' => 0,
                'allow_overdraft' => false,
                'accrues_interest' => true,
                'interest_rate' => 3.0,
                'description' => 'Escrow account for secure transactions',
                'active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'code' => 'CURRENT',
                'name' => 'Current Account',
                'currency' => 'NGN',
                'min_balance' => 1000,
                'allow_overdraft' => true,
                'overdraft_limit' => 50000,
                'accrues_interest' => false,
                'interest_rate' => 0,
                'description' => 'Current account with overdraft facility',
                'active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'code' => 'TERM',
                'name' => 'Term Deposit Account',
                'currency' => 'NGN',
                'min_balance' => 10000,
                'allow_overdraft' => false,
                'accrues_interest' => true,
                'interest_rate' => 5.0,
                'description' => 'Fixed term deposit account',
                'active' => true,
            ],
        ];

        foreach ($types as $type) {
            AccountType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}