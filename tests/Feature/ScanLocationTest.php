<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\Pass;
use App\Models\PassType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScanLocationTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private Pass $pass;

    protected function setUp(): void
    {
        parent::setUp();

        $type = PassType::factory()->create();
        $this->agent = User::factory()->create(['name' => 'Awa Agent']);
        $type->event->staff()->attach($this->agent->id, ['role' => StaffRole::Agent->value]);
        $this->pass = Pass::factory()->registered()->create(['pass_type_id' => $type->id]);
        Sanctum::actingAs($this->agent);
    }

    public function test_scan_stores_agent_position(): void
    {
        $this->postJson('/api/v1/scans', ['code' => $this->pass->token, 'latitude' => 5.3197012, 'longitude' => -4.0167293, 'accuracy' => 12.4])
            ->assertCreated()
            ->assertJsonPath('scan.location.latitude', 5.3197012)
            ->assertJsonPath('scan.location.longitude', -4.0167293)
            ->assertJsonPath('scan.location.accuracy', 12);

        $this->assertDatabaseHas('scans', ['pass_id' => $this->pass->id, 'location_accuracy' => 12]);
    }

    public function test_scan_without_position_is_still_recorded(): void
    {
        $this->postJson('/api/v1/scans', ['code' => $this->pass->token])
            ->assertCreated()
            ->assertJsonPath('scan.location', null);
    }

    public function test_invalid_position_is_rejected(): void
    {
        $this->postJson('/api/v1/scans', ['code' => $this->pass->token, 'latitude' => 120, 'longitude' => 2])
            ->assertJsonValidationErrors('latitude');
        $this->postJson('/api/v1/scans', ['code' => $this->pass->token, 'latitude' => 5.3])
            ->assertJsonValidationErrors('longitude');

        $this->postJson('/api/v1/scans/batch', ['scans' => [[
            'client_uuid' => (string) Str::uuid(), 'code' => $this->pass->token, 'result' => 'granted',
            'direction' => 'in', 'scanned_at' => now()->toIso8601String(), 'longitude' => -4.01,
        ]]])->assertJsonValidationErrors('scans.0.latitude');
    }

    public function test_offline_scans_keep_their_position(): void
    {
        $this->postJson('/api/v1/scans/batch', ['scans' => [[
            'client_uuid' => (string) Str::uuid(), 'code' => $this->pass->token, 'result' => 'granted',
            'direction' => 'in', 'scanned_at' => now()->toIso8601String(), 'latitude' => 5.32, 'longitude' => -4.01, 'accuracy' => 30,
        ]]])->assertOk();

        $this->assertDatabaseHas('scans', ['pass_id' => $this->pass->id, 'offline' => true, 'location_accuracy' => 30]);
    }

    public function test_admin_sees_each_agent_history_with_positions(): void
    {
        $this->postJson('/api/v1/scans', ['code' => $this->pass->token, 'latitude' => 5.3197012, 'longitude' => -4.0167293]);
        $this->postJson('/api/v1/scans', ['code' => 'inconnu1234', 'result' => 'denied']);

        $other = User::factory()->create();
        $this->pass->event->staff()->attach($other->id, ['role' => StaffRole::Chief->value]);
        Sanctum::actingAs($other);
        $this->postJson('/api/v1/scans', ['code' => $this->pass->token]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('users.scans', $this->agent))
            ->assertOk()
            ->assertSee('Historique de Awa Agent')
            ->assertSee($this->pass->number)
            ->assertSee('https://www.google.com/maps?q=5.3197012,-4.0167293', false)
            ->assertSee('scan-map')
            ->assertViewHas('summary', fn (array $summary) => $summary['total'] === 2 && $summary['denied'] === 1 && $summary['located'] === 1);

        $this->get(route('users.scans', [$this->agent, 'result' => 'denied']))
            ->assertViewHas('summary', fn (array $summary) => $summary['total'] === 1);

        $this->get(route('users.scans', [$this->agent, 'event' => Event::factory()->create()->id]))
            ->assertViewHas('summary', fn (array $summary) => $summary['total'] === 0);
    }

    public function test_only_admin_sees_agent_history(): void
    {
        $chief = User::factory()->create();
        $this->pass->event->staff()->attach($chief->id, ['role' => StaffRole::Chief->value]);

        $this->actingAs($chief)->get(route('users.scans', $this->agent))->assertForbidden();
    }
}
