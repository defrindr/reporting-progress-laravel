<?php

namespace Database\Factories;

use App\Models\GlobalHoliday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlobalHoliday>
 */
class GlobalHolidayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'holiday_date' => fake()->dateTimeBetween('-1 year', '+1 year'),
            'name' => fake()->holidayName(),
            'country_code' => 'ID',
            'year' => (int) fake()->year(),
            'source' => fake()->randomElement(['nager', 'libur']),
        ];
    }
}
