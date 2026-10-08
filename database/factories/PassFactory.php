<?php

namespace Database\Factories;

use App\Enums\Direction;
use App\Enums\PassStatus;
use App\Models\Pass;
use App\Models\PassType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pass>
 */
class PassFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pass_type_id' => PassType::factory(),
            'event_id' => fn (array $attributes) => PassType::find($attributes['pass_type_id'])->event_id,
            'token' => Str::random(12),
            'sequence' => fake()->unique()->numberBetween(1, 99999),
            'number' => fn (array $attributes) => 'T-'.$attributes['sequence'],
            'status' => PassStatus::Pending,
            'presence' => Direction::Out,
        ];
    }

    /**
     * Pass avec un véhicule enregistré.
     */
    public function registered(array $vehicle = []): static
    {
        return $this->state(fn () => ['status' => PassStatus::Registered, 'registered_at' => now()])
            ->afterCreating(fn (Pass $pass) => $pass->vehicle()->create([
                'event_id' => $pass->event_id,
                'plate' => fake()->unique()->bothify('#### ?? ##'),
                'brand' => 'Toyota',
                'color' => 'Blanc',
                'phone' => '+2250700000000',
                ...$vehicle,
            ]));
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['status' => PassStatus::Revoked]);
    }
}
