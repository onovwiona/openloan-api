<?php

namespace Database\Factories;

use App\Models\SignupAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SignupAttempt>
 */
class SignupAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'status' => 'pending',
            'attempted_at' => now(),
        ];
    }

    /**
     * Indicate that the signup attempt failed.
     *
     * @return static
     */
    public function failed(string $reason = 'Invalid details'): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Indicate that the signup attempt is blocked.
     *
     * @return static
     */
    public function blocked(string $reason = 'Suspected fraud'): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'blocked',
            'blocked_reason' => $reason,
        ]);
    }
}
