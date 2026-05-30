<?php

namespace Database\Factories;

use App\Models\LoanApplication;
use App\Models\User;
use App\Models\LoanProduct;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanApplication>
 */
class LoanApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->uuid(),
            'customer_id' => User::factory(),
            'loan_product_id' => LoanProduct::factory(),
            'account_id' => Account::factory(),
            'application_no' => 'LA' . fake()->unique()->numberBetween(100000, 999999),
            'requested_amount' => fake()->numberBetween(1000, 50000),
            'requested_tenure' => fake()->numberBetween(6, 60),
            'repayment_plan' => 'monthly',
            'monthly_income' => fake()->numberBetween(50000, 500000),
            'payroll_gross' => fake()->numberBetween(50000, 500000),
            'payroll_net' => fake()->numberBetween(40000, 400000),
            'employment_status' => fake()->randomElement(['employed', 'self_employed', 'unemployed']),
            'purpose' => fake()->randomElement(['business', 'education', 'medical', 'personal', 'home_improvement']),
            'status' => 'draft',
            'rejection_reason' => null,
            'reviewed_by' => null,
            'submitted_at' => null,
            'reviewed_at' => null,
        ];
    }
}