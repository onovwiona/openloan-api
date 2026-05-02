<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LoanProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'id' => (string) Str::uuid(),
                'code' => 'SALARY_ADV',
                'name' => 'Salary Advance',
                'description' => 'Short-term advance against salary',
                'requires_account' => true,
                'min_amount' => 5000,
                'max_amount' => 100000,
                'interest_type' => 'flat',
                'interest_rate' => 5,
                'tenure_min_months' => 1,
                'tenure_max_months' => 3,
                'processing_fee' => 1,
                'penalty_rate' => 2,
                'allow_early_repayment' => true,
                'early_repayment_penalty' => 0,
                'requires_guarantor' => false,
                'min_guarantors' => 0,
                'requires_collateral' => false,
                'active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'code' => 'PERSONAL',
                'name' => 'Personal Loan',
                'description' => 'Unsecured personal loan for any purpose',
                'requires_account' => true,
                'min_amount' => 50000,
                'max_amount' => 5000000,
                'interest_type' => 'reducing',
                'interest_rate' => 24,
                'tenure_min_months' => 6,
                'tenure_max_months' => 36,
                'processing_fee' => 1,
                'penalty_rate' => 1,
                'allow_early_repayment' => true,
                'early_repayment_penalty' => 2,
                'requires_guarantor' => true,
                'min_guarantors' => 1,
                'requires_collateral' => false,
                'active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'code' => 'SME',
                'name' => 'SME Loan',
                'description' => 'Business loan for small and medium enterprises',
                'requires_account' => true,
                'min_amount' => 100000,
                'max_amount' => 10000000,
                'interest_type' => 'reducing',
                'interest_rate' => 18,
                'tenure_min_months' => 6,
                'tenure_max_months' => 48,
                'processing_fee' => 1.5,
                'penalty_rate' => 1.5,
                'allow_early_repayment' => true,
                'early_repayment_penalty' => 1,
                'requires_guarantor' => true,
                'min_guarantors' => 2,
                'requires_collateral' => true,
                'active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'code' => 'BNPL',
                'name' => 'Buy Now Pay Later',
                'description' => 'Short-term credit for purchases',
                'requires_account' => true,
                'min_amount' => 1000,
                'max_amount' => 200000,
                'interest_type' => 'flat',
                'interest_rate' => 3,
                'tenure_min_months' => 1,
                'tenure_max_months' => 6,
                'processing_fee' => 0,
                'penalty_rate' => 3,
                'allow_early_repayment' => true,
                'early_repayment_penalty' => 0,
                'requires_guarantor' => false,
                'min_guarantors' => 0,
                'requires_collateral' => false,
                'active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'code' => 'COOP',
                'name' => 'Cooperative Loan',
                'description' => 'Loan for cooperative society members',
                'requires_account' => true,
                'min_amount' => 10000,
                'max_amount' => 1000000,
                'interest_type' => 'reducing',
                'interest_rate' => 12,
                'tenure_min_months' => 3,
                'tenure_max_months' => 24,
                'processing_fee' => 0.5,
                'penalty_rate' => 1,
                'allow_early_repayment' => true,
                'early_repayment_penalty' => 0,
                'requires_guarantor' => false,
                'min_guarantors' => 0,
                'requires_collateral' => false,
                'active' => true,
            ],
        ];

        foreach ($products as $product) {
            LoanProduct::updateOrCreate(
                ['code' => $product['code']],
                $product
            );
        }
    }
}