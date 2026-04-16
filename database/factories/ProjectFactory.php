<?php

namespace Database\Factories;

use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSpec;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_spec_id' => ProjectSpec::factory(),
            'period_id' => Period::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'due_date' => fake()->dateTimeBetween('now', '+1 month'),
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'assignee_id' => User::factory(),
            'created_by' => User::factory(),
            'status' => 'todo',
        ];
    }

    public function todo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'todo',
        ]);
    }

    public function doing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'doing',
        ]);
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'done',
        ]);
    }
}
