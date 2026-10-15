<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'avatar_path' => fake()->boolean(40) ? 'avatars/'.fake()->uuid().'.jpg' : null,
            'phone' => fake()->optional(0.6)->e164PhoneNumber(),
            'bio' => fake()->optional(0.5)->sentences(2, true),
        ];
    }
}
