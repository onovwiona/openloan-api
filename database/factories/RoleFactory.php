<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'guard_name' => 'web',
        ];
    }

    /**
     * Create admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'admin',
            'guard_name' => 'web',
        ]);
    }

    /**
     * Create marketer role.
     */
    public function marketer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'marketer',
            'guard_name' => 'web',
        ]);
    }

    /**
     * Create office role.
     */
    public function office(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'office',
            'guard_name' => 'web',
        ]);
    }

    /**
     * Create customer role.
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'customer',
            'guard_name' => 'web',
        ]);
    }
}
