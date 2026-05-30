<?php

namespace Database\Seeders;

use App\Models\AccountType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CooperativeAccountTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'COOP_SAV_MONTHLY',
                'name' => 'Cooperative Savings - Monthly Thrift',
                'description' => 'Regular monthly savings with weekly/interval thrift, ROI 0%',
            ],
            [
                'code' => 'COOP_SAV_6MONTH',
                'name' => 'Cooperative Savings - 6 Months Streak',
                'description' => '6-month streak savings; 50% of one interval payment added as ROI at term end',
            ],
            [
                'code' => 'COOP_SAV_11MONTH',
                'name' => 'Cooperative Savings - 11 Months Streak',
                'description' => '11-month streak savings; 100% of one interval payment added as ROI at term end',
            ],
            [
                'code' => 'COOP_SAV_12MONTH',
                'name' => 'Cooperative Savings - 12 Months Streak',
                'description' => '12-month streak savings; 100% of one interval payment added as ROI at term end',
            ],
        ];

        foreach ($types as $t) {
            AccountType::updateOrCreate(
                ['code' => $t['code']],
                [
                    'name' => $t['name'],
                    'currency' => 'NGN',
                    'account_category' => 'CUSTOMER_DEPOSIT',
                    'normal_balance' => 'DEBIT',
                    'min_balance' => 0,
                    'max_balance' => 999999999.99,
                    'allow_overdraft' => false,
                    'overdraft_limit' => 0,
                    'accrues_interest' => false,
                    'interest_rate' => 0,
                    'description' => $t['description'],
                    'is_customer_visible' => true,
                    'supports_deposit' => true,
                    'supports_withdrawal' => true,
                    'supports_transfer' => true,
                    'requires_kyc' => true,
                    'active' => true,
                ]
            );
        }
    }
}
