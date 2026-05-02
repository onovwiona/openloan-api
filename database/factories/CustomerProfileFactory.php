<?php

namespace Database\Factories;

use App\Models\CustomerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerProfile>
 */
class CustomerProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'address' => fake()->address(),
            'dob' => fake()->date('Y-m-d', '-18 years'),
            'employment_status' => fake()->randomElement(['employed', 'self-employed', 'unemployed']),
            'monthly_income' => fake()->randomFloat(2, 50000, 500000),
            'kyc_status' => 'verified',
            'kyc_verified_at' => now(),
        ];
    }

    /**
     * Indicate that the profile is pending verification.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'kyc_status' => 'pending',
            'kyc_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the profile is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'kyc_status' => 'rejected',
            'kyc_verified_at' => null,
        ]);
    }
}
