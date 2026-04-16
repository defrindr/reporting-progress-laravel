<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\Period;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Period>
 */
class PeriodFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 year', 'now');
        $endDate = fake()->dateTimeBetween($startDate, '+1 year');

        return [
            'institution_id' => Institution::factory(),
            'type' => Period::TYPE_INTERNSHIP,
            'name' => fake()->sentence(3),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'holidays' => [],
        ];
    }

    public function internship(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Period::TYPE_INTERNSHIP,
        ]);
    }

    public function sprint(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Period::TYPE_SPRINT,
        ]);
    }

    public function withHolidays(array $holidays): static
    {
        return $this->state(fn (array $attributes) => [
            'holidays' => $holidays,
        ]);
    }
}
