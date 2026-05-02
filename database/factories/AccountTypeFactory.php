<?php

namespace Database\Factories;

use App\Models\AccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountType>
 */
class AccountTypeFactory extends Factory
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
            'name' => fake()->words(2, true),
            'currency' => 'NGN',
            'min_balance' => fake()->randomFloat(2, 0, 1000),
            'max_balance' => fake()->randomFloat(2, 10000, 100000),
            'allow_overdraft' => fake()->boolean(),
            'overdraft_limit' => fake()->randomFloat(2, 0, 5000),
            'accrues_interest' => fake()->boolean(),
            'interest_rate' => fake()->randomFloat(2, 0, 10),
            'description' => fake()->sentence(),
            'active' => true,
        ];
    }
}