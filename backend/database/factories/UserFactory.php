<?php

namespace Database\Factories;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Model associated with this factory.
     */
    protected $model = User::class;

    /**
     * Cached password hash.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),

            'email' => fake()
                ->unique()
                ->safeEmail(),

            'email_verified_at' => now(),

            'password' => static::$password ??= Hash::make(
                'Password123'
            ),

            'timezone' => 'UTC',

            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Create a user whose email has not been verified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}