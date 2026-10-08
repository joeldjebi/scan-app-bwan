<?php

namespace Tests\Feature;

use App\Enums\PassStatus;
use App\Models\Event;
use App\Models\Pass;
use App\Models\PassType;
use App\Services\PassGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private array $vehicle = [
        'plate' => '1234 ab 01',
        'brand' => 'Toyota',
        'color' => 'blanc',
        'phone' => '+225 07 00 00 00 01',
    ];

    public function test_generator_creates_numbered_passes_with_unique_tokens(): void
    {
        $event = Event::factory()->create(['code' => 'FEST']);
        $type = $event->passTypes()->create(['name' => 'VIP', 'code' => 'VIP', 'color' => '#000000']);

        app(PassGenerator::class)->generate($type, 3);
        app(PassGenerator::class)->generate($type, 2);

        $passes = $type->passes()->orderBy('sequence')->get();
        $this->assertSame(['FEST-VIP-0001', 'FEST-VIP-0002', 'FEST-VIP-0003', 'FEST-VIP-0004', 'FEST-VIP-0005'], $passes->pluck('number')->all());
        $this->assertCount(5, $passes->pluck('token')->unique());
    }

    public function test_user_registers_vehicle_with_qr_link(): void
    {
        $pass = Pass::factory()->create();

        $this->get(route('public.pass', $pass->token))->assertOk()->assertSee('Enregistrer mon véhicule');

        $this->post(route('public.pass.register', $pass->token), $this->vehicle)
            ->assertRedirect(route('public.pass', $pass->token));

        $pass->refresh();
        $this->assertSame(PassStatus::Registered, $pass->status);
        $this->assertSame('1234 AB 01', $pass->vehicle->plate);
        $this->assertSame('Blanc', $pass->vehicle->color);
        $this->assertSame('+2250700000001', $pass->vehicle->phone);

        $this->get(route('public.pass', $pass->token))->assertSee('1234 AB 01')->assertDontSee('Enregistrer mon véhicule');
    }

    public function test_other_brand_requires_custom_name(): void
    {
        $pass = Pass::factory()->create();

        $this->post(route('public.pass.register', $pass->token), [...$this->vehicle, 'brand' => 'Autre'])
            ->assertSessionHasErrors('brand_other');

        $this->post(route('public.pass.register', $pass->token), [...$this->vehicle, 'brand' => 'Autre', 'brand_other' => 'Lada'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Lada', $pass->fresh()->vehicle->brand);
    }

    public function test_registered_pass_cannot_be_changed_by_user(): void
    {
        $pass = Pass::factory()->registered()->create();

        $this->post(route('public.pass.register', $pass->token), $this->vehicle)->assertForbidden();
    }

    public function test_same_vehicle_cannot_hold_two_passes_of_same_event(): void
    {
        $type = PassType::factory()->create();
        Pass::factory()->registered(['plate' => '1234-AB-01'])->create(['pass_type_id' => $type->id]);
        $pass = Pass::factory()->create(['pass_type_id' => $type->id]);

        $this->post(route('public.pass.register', $pass->token), $this->vehicle)
            ->assertSessionHasErrors('plate');
    }

    public function test_page_shows_event_period_and_why_registration_is_closed(): void
    {
        $event = Event::factory()->create([
            'starts_at' => '2026-10-08 11:18', 'ends_at' => now()->addDays(2)->setTime(23, 0), 'status' => 'active',
        ]);
        $pass = Pass::factory()->create(['pass_type_id' => PassType::factory()->create(['event_id' => $event->id])->id]);

        $this->get(route('public.pass', $pass->token))->assertSee('Du jeudi 8 octobre')->assertSee('Enregistrer mon véhicule');

        $event->update(['status' => 'closed']);
        $this->get(route('public.pass', $pass->token))
            ->assertSee("L'organisateur a clôturé les enregistrements", false)
            ->assertDontSee('Cet événement est terminé');

        $event->update(['ends_at' => now()->subHour()]);
        $this->get(route('public.pass', $pass->token))->assertSee('Cet événement est terminé');
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->get('/p/doesnotexist')->assertNotFound();
    }
}
