<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\User;
use App\Models\Account;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(1000, 50000);
        $interestRate = fake()->randomFloat(2, 5, 25);
        $tenure = fake()->numberBetween(6, 60);
        $totalInterest = ($amount * $interestRate * $tenure) / (100 * 12);
        $totalRepayment = $amount + $totalInterest;

        return [
            'id' => fake()->uuid(),
            'loan_application_id' => LoanApplication::factory(),
            'customer_id' => User::factory(),
            'account_id' => Account::factory(),
            'loan_no' => 'LN' . fake()->unique()->numberBetween(100000, 999999),
            'principal' => $amount,
            'interest_rate' => $interestRate,
            'tenure_months' => $tenure,
            'total_interest' => $totalInterest,
            'total_repayment' => $totalRepayment,
            'disbursed_amount' => 0,
            'outstanding_principal' => $amount,
            'outstanding_interest' => $totalInterest,
            'outstanding_total' => $totalRepayment,
            'status' => 'pending',
            'disbursed_at' => null,
            'maturity_date' => now()->addMonths($tenure),
            'first_payment_date' => now()->addMonth(),
            'approved_by' => null,
            'approved_at' => null,
            'disbursed_by' => null,
        ];
    }
}