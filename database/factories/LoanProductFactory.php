<?php

namespace Database\Factories;

use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanProduct>
 */
class LoanProductFactory extends Factory
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
            'code' => fake()->unique()->word(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'requires_account' => fake()->boolean(),
            'repayment_account_type_id' => null,
            'min_amount' => fake()->numberBetween(100, 1000),
            'max_amount' => fake()->numberBetween(10000, 100000),
            'interest_type' => 'reducing',
            'interest_rate' => fake()->randomFloat(2, 5, 25),
            'tenure_min_months' => fake()->numberBetween(1, 6),
            'tenure_max_months' => fake()->numberBetween(12, 60),
            'processing_fee' => fake()->randomFloat(2, 0, 5),
            'penalty_rate' => fake()->randomFloat(2, 0, 2),
            'insurance_fee' => fake()->randomFloat(2, 0, 2),
            'legal_fee' => fake()->randomFloat(2, 0, 1),
            'allow_early_repayment' => true,
            'early_repayment_penalty' => fake()->randomFloat(2, 0, 1),
            'requires_guarantor' => false,
            'min_guarantors' => 0,
            'requires_collateral' => false,
            'active' => true,
        ];
    }
}