<?php

namespace Database\Seeders;

use App\Models\CommissionRule;

use Illuminate\Database\Seeder;

class CommissionRuleSeeder extends Seeder

{

    public function run(): void

    {

        CommissionRule::updateOrCreate(

            ['name' => 'Marketer Referral Signup Bonus'],

            [

                'beneficiary_role' => 'marketer',

                'trigger_type' => 'signup',

                'amount_type' => 'percentage',

                'amount_value' => 10,

                'minimum_amount' => 0,

                'is_active' => true,

                'starts_at' => now()->subMonth(),

                'ends_at' => now()->addYear(),

            ]

        );

        CommissionRule::updateOrCreate(

            ['name' => 'Marketer Loan Referral Bonus'],

            [

                'beneficiary_role' => 'marketer',

                'trigger_type' => 'loan_disbursed',

                'amount_type' => 'percentage',

                'amount_value' => 2.5,

                'minimum_amount' => 50000,

                'is_active' => true,

                'starts_at' => now()->subMonth(),

                'ends_at' => now()->addYear(),

            ]

        );

        CommissionRule::updateOrCreate(

            ['name' => 'Marketer General Referral Bonus'],

            [

                'beneficiary_role' => 'marketer',

                'trigger_type' => 'referral_bonus',

                'amount_type' => 'percentage',

                'amount_value' => 5,

                'minimum_amount' => 10000,

                'is_active' => true,

                'starts_at' => now()->subMonth(),

                'ends_at' => now()->addYear(),

            ]

        );

    }

}
