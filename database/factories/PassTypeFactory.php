<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\PassType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PassType>
 */
class PassTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'VIP',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'color' => fake()->hexColor(),
        ];
    }
}
