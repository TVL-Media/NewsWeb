<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => (string) fake()->unique()->numberBetween(6000000000, 9999999999),
            'language' => 'en',
            'onboarded_at' => now(),
            'last_active_at' => now(),
        ];
    }

    /** A brand-new user who hasn't finished onboarding. */
    public function fresh(): static
    {
        return $this->state(fn () => ['onboarded_at' => null, 'name' => null]);
    }
}
