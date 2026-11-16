<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function withWebhook(string $url = 'https://partner.example.com/webhooks'): static
    {
        return $this->state(fn (array $attributes) => ['webhook_url' => $url])
            ->afterCreating(fn (Partner $partner) => $partner->rotateWebhookSecret());
    }
}
