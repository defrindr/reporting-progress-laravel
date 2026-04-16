<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'body' => fake()->paragraph(),
            'user_id' => User::factory(),
            'commentable_id' => Project::factory(),
            'commentable_type' => Project::class,
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'commentable_id' => $project->id,
            'commentable_type' => Project::class,
        ]);
    }
}
