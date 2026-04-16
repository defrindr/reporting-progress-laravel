<?php

namespace Database\Factories;

use App\Models\Logbook;
use App\Models\Period;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Logbook>
 */
class LogbookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'period_id' => Period::factory(),
            'report_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'done_tasks' => fake()->paragraph(),
            'next_tasks' => fake()->paragraph(),
            'appendix_link' => fake()->optional()->url(),
            'status' => fake()->randomElement(['draft', 'submitted', 'approved']),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }
}
