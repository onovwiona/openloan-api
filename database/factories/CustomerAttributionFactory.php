<?php

namespace Database\Factories;

use App\Models\CustomerAttribution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAttribution>
 */
class CustomerAttributionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_type' => 'organic',
            'status' => 'verified',
            'captured_at' => now(),
        ];
    }

    /**
     * Attribution from marketer.
     */
    public function fromMarketer(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => 'marketer',
        ]);
    }

    /**
     * Attribution from staff/office.
     */
    public function fromStaff(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => 'staff',
        ]);
    }

    /**
     * Attribution from walk-in.
     */
    public function walkIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => 'walk_in',
        ]);
    }

    /**
     * Attribution from organic.
     */
    public function organic(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => 'organic',
        ]);
    }
}
