<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Associate with an existing or new user automatically.
            'user_id' => User::factory(),
            'title'   => fake()->sentence(6),
            'body'    => fake()->paragraphs(3, true),
            'status'  => PostStatus::Draft,
            'published_at' => null,
        ];
    }

    /**
     * Factory state: a published post.
     * Usage: Post::factory()->published()->create()
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => PostStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * Factory state: explicitly a draft (the default, but named for clarity).
     * Usage: Post::factory()->draft()->create()
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => PostStatus::Draft,
            'published_at' => null,
        ]);
    }
}
