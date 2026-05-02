<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use App\Models\AccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'account_type_id' => AccountType::factory(),
            'account_no' => fake()->unique()->numberBetween(1000000000, 9999999999),
            'name' => fake()->words(2, true),
            'currency' => 'USD',
            'status' => 'active',
            'opened_at' => fake()->dateTimeBetween('-2 years', 'now'),
        ];
    }
}