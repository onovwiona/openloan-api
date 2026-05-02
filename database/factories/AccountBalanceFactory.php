<?php

namespace Database\Factories;

use App\Models\AccountBalance;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountBalance>
 */
class AccountBalanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'available_balance' => fake()->numberBetween(0, 100000),
            'ledger_balance' => fake()->numberBetween(0, 100000),
            'hold_balance' => 0,
            'uncleared_balance' => 0,
            'as_at' => now(),
        ];
    }
}