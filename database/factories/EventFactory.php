<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+2 months');

        return [
            'name' => fake()->randomElement(['Festival', 'Gala', 'Concert', 'Forum']).' '.fake()->city(),
            'code' => strtoupper(fake()->unique()->bothify('EV##??')),
            'location' => fake()->city(),
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+8 hours'),
            'status' => EventStatus::Active,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EventStatus::Closed]);
    }
}
