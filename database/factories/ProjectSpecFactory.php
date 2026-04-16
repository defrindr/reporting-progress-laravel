<?php

namespace Database\Factories;

use App\Models\ProjectSpec;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectSpec>
 */
class ProjectSpecFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'specification' => fake()->paragraphs(3, true),
            'created_by' => User::factory(),
        ];
    }
}
