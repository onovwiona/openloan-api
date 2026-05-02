<?php

namespace Database\Factories;

use App\Models\EmployeeProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => 'EMP' . fake()->unique()->numerify('####'),
            'department' => fake()->randomElement(['Sales', 'Marketing', 'Operations', 'Finance', 'IT', 'HR']),
            'hire_date' => fake()->date('Y-m-d', '-5 years'),
            'salary' => fake()->randomFloat(2, 50000, 500000),
        ];
    }

    /**
     * Create marketer employee.
     */
    public function marketer(): static
    {
        return $this->state(fn (array $attributes) => [
            'department' => 'Marketing',
        ]);
    }

    /**
     * Create office staff employee.
     */
    public function office(): static
    {
        return $this->state(fn (array $attributes) => [
            'department' => 'Operations',
        ]);
    }
}
