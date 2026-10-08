<?php

namespace Tests\Feature;

use App\Enums\Direction;
use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\Pass;
use App\Models\PassType;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScanApiTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private PassType $type;

    private User $agent;

    private User $chief;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create();
        $this->type = PassType::factory()->create(['event_id' => $this->event->id]);
        $this->agent = User::factory()->create();
        $this->chief = User::factory()->create();
        $this->event->staff()->attach([
            $this->agent->id => ['role' => StaffRole::Agent->value],
            $this->chief->id => ['role' => StaffRole::Chief->value],
        ]);
    }

    private function pass(string $state = 'registered', ?PassType $type = null): Pass
    {
        $factory = Pass::factory()->state(['pass_type_id' => ($type ?? $this->type)->id]);

        return match ($state) {
            'registered' => $factory->registered()->create(),
            'revoked' => $factory->registered()->revoked()->create(),
            default => $factory->create(),
        };
    }

    public function test_login_returns_token(): void
    {
        $this->postJson('/api/v1/auth/login', ['phone' => $this->agent->phone, 'password' => 'password', 'device_name' => 'phone'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'role']]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create();

        $this->postJson('/api/v1/auth/login', ['phone' => $user->phone, 'password' => 'password', 'device_name' => 'phone'])
            ->assertUnprocessable();
    }

    public function test_agent_verifies_any_pass_of_his_events_without_choosing_event(): void
    {
        $pass = $this->pass();
        Sanctum::actingAs($this->agent);

        $this->postJson('/api/v1/verify', ['code' => $pass->url()])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('can_force', false)
            ->assertJsonPath('event.id', $this->event->id)
            ->assertJsonPath('pass.number', $pass->number)
            ->assertJsonPath('pass.vehicle.plate', $pass->vehicle->plate)
            ->assertJsonPath('pass.next_direction', 'in');
    }

    public function test_verify_rejects_invalid_passes(): void
    {
        Sanctum::actingAs($this->agent);

        $this->postJson('/api/v1/verify', ['code' => $this->pass('pending')->token])
            ->assertJsonPath('valid', false)->assertJsonPath('reason', 'not_registered');

        $this->postJson('/api/v1/verify', ['code' => $this->pass('revoked')->token])
            ->assertJsonPath('valid', false)->assertJsonPath('reason', 'revoked');

        $this->postJson('/api/v1/verify', ['code' => 'https://example.com/p/nope12345678'])
            ->assertJsonPath('valid', false)->assertJsonPath('reason', 'unknown_pass');
    }

    public function test_pass_of_an_event_the_agent_is_not_assigned_to_is_hidden(): void
    {
        $other = Pass::factory()->registered()->create();
        Sanctum::actingAs($this->agent);

        $this->postJson('/api/v1/verify', ['code' => $other->token])
            ->assertJsonPath('valid', false)
            ->assertJsonPath('reason', 'not_assigned')
            ->assertJsonPath('pass', null)
            ->assertJsonPath('event', null);

        $this->postJson('/api/v1/scans', ['code' => $other->token])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'not_assigned');

        $this->assertSame(Direction::Out, $other->fresh()->presence);
    }

    public function test_successive_validations_alternate_entry_and_exit(): void
    {
        $pass = $this->pass();
        Sanctum::actingAs($this->agent);

        foreach (['in', 'out', 'in'] as $expected) {
            $this->travel(1)->minutes();
            $this->postJson('/api/v1/scans', ['code' => $pass->token])
                ->assertCreated()
                ->assertJsonPath('scan.result', 'granted')
                ->assertJsonPath('scan.direction', $expected)
                ->assertJsonPath('pass.presence', $expected);
        }

        $this->assertSame(Direction::In, $pass->fresh()->presence);
        $this->assertSame(3, $pass->scans()->count());
        $this->assertTrue($pass->scans()->get()->every(fn (Scan $scan) => $scan->event_id === $this->event->id));
    }

    public function test_denied_scan_does_not_change_presence(): void
    {
        $pass = $this->pass();
        Sanctum::actingAs($this->agent);

        $this->postJson('/api/v1/scans', ['code' => $pass->token, 'result' => 'denied', 'reason' => 'Plaque différente'])
            ->assertCreated()
            ->assertJsonPath('scan.result', 'denied');

        $this->assertSame(Direction::Out, $pass->fresh()->presence);
    }

    public function test_agent_cannot_force_but_chief_can_on_his_event_only(): void
    {
        $pass = $this->pass('pending');

        Sanctum::actingAs($this->agent);
        $this->postJson('/api/v1/scans', ['code' => $pass->token, 'force' => true])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'not_registered')
            ->assertJsonPath('can_force', false);

        Sanctum::actingAs($this->chief);
        $this->postJson('/api/v1/verify', ['code' => $pass->token])->assertJsonPath('can_force', true);
        $this->postJson('/api/v1/scans', ['code' => $pass->token])
            ->assertUnprocessable()
            ->assertJsonPath('can_force', true);
        $this->postJson('/api/v1/scans', ['code' => $pass->token, 'force' => true])
            ->assertCreated()
            ->assertJsonPath('scan.forced', true);

        // Chef d'un autre événement : aucun droit.
        $foreign = Pass::factory()->create();
        $this->postJson('/api/v1/scans', ['code' => $foreign->token, 'force' => true])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'not_assigned')
            ->assertJsonPath('can_force', false);
    }

    public function test_scan_is_idempotent_with_client_uuid(): void
    {
        $pass = $this->pass();
        $uuid = (string) Str::uuid();
        Sanctum::actingAs($this->agent);

        $this->postJson('/api/v1/scans', ['code' => $pass->token, 'client_uuid' => $uuid])->assertCreated();
        $this->postJson('/api/v1/scans', ['code' => $pass->token, 'client_uuid' => $uuid])->assertOk();

        $this->assertSame(1, $pass->scans()->count());
        $this->assertSame(Direction::In, $pass->fresh()->presence);
    }

    public function test_repeated_validation_within_seconds_is_not_duplicated(): void
    {
        $pass = $this->pass();
        Sanctum::actingAs($this->agent);

        // Double appui ou requête renvoyée après une coupure, sans client_uuid.
        $first = $this->postJson('/api/v1/scans', ['code' => $pass->token])->assertCreated();
        $this->travel(5)->seconds();
        $this->postJson('/api/v1/scans', ['code' => $pass->token])
            ->assertOk()
            ->assertJsonPath('scan.id', $first->json('scan.id'));

        $this->assertSame(1, $pass->scans()->count());
        $this->assertSame(Direction::In, $pass->fresh()->presence);

        // Passé le délai, c'est un nouveau passage (sortie).
        $this->travel(config('parking.scan_dedup_seconds') + 1)->seconds();
        $this->postJson('/api/v1/scans', ['code' => $pass->token])->assertCreated()->assertJsonPath('scan.direction', 'out');
    }

    public function test_offline_batch_without_client_uuid_is_not_duplicated_when_resent(): void
    {
        $pass = $this->pass();
        Sanctum::actingAs($this->agent);
        $scans = [
            ['code' => $pass->token, 'result' => 'granted', 'direction' => 'in', 'scanned_at' => now()->subMinutes(10)->toIso8601String()],
            ['plate' => $pass->vehicle->plate, 'result' => 'granted', 'direction' => 'out', 'scanned_at' => now()->subMinutes(2)->toIso8601String()],
        ];

        $this->postJson('/api/v1/scans/batch', ['scans' => $scans])
            ->assertOk()
            ->assertJsonPath('created', 2)
            ->assertJsonPath('results.0.index', 0)
            ->assertJsonPath('results.1.index', 1);

        $this->postJson('/api/v1/scans/batch', ['scans' => $scans])
            ->assertJsonPath('created', 0)
            ->assertJsonPath('duplicates', 2);

        $this->assertSame(2, $pass->scans()->count());
    }

    public function test_history_returns_totals_and_groups_my_scans_by_vehicle(): void
    {
        $pass = $this->pass();
        $other = $this->pass();
        Sanctum::actingAs($this->chief);
        $this->postJson('/api/v1/scans', ['code' => $pass->token]); // entrée par un autre agent

        Sanctum::actingAs($this->agent);
        $this->postJson('/api/v1/scans', ['code' => $pass->token]);            // sortie
        $this->travel(1)->minutes();
        $this->postJson('/api/v1/scans', ['plate' => $pass->vehicle->plate]); // entrée (saisie manuelle)
        $this->travel(1)->minutes();
        $this->postJson('/api/v1/scans', ['code' => 'inconnu123456', 'result' => 'denied']);
        $this->travel(1)->minutes();
        $this->postJson('/api/v1/scans', ['code' => $other->token]);           // véhicule vu en dernier

        $response = $this->getJson('/api/v1/scans/history')
            ->assertOk()
            ->assertJsonPath('summary', ['total' => 4, 'entries' => 2, 'exits' => 1, 'denied' => 1, 'manual' => 1, 'vehicles' => 2])
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);

        // Du véhicule vu le plus récemment au plus ancien.
        $response->assertJsonPath('data.0.pass.id', $other->id)
            ->assertJsonPath('data.1.pass', null)
            ->assertJsonPath('data.1.counts.denied', 1)
            ->assertJsonPath('data.2.pass.number', $pass->number)
            ->assertJsonPath('data.2.vehicle.plate', $pass->vehicle->plate)
            ->assertJsonPath('data.2.event.name', $this->event->name)
            ->assertJsonPath('data.2.counts', ['total' => 2, 'entries' => 1, 'exits' => 1, 'denied' => 0, 'manual' => 1])
            ->assertJsonCount(2, 'data.2.scans')
            ->assertJsonPath('data.2.scans.0.method', 'plate')
            ->assertJsonPath('data.2.scans.0.direction', 'in')
            ->assertJsonPath('data.2.scans.1.direction', 'out');

        // Filtres : événement et période.
        $this->getJson('/api/v1/scans/history?event='.$this->event->id)->assertJsonPath('summary.vehicles', 2)->assertJsonPath('summary.denied', 0);
        $this->getJson('/api/v1/scans/history?from='.now()->addDay()->toDateString())->assertJsonPath('summary.total', 0)->assertJsonCount(0, 'data');
    }

    public function test_offline_batch_is_applied_in_order_and_idempotent(): void
    {
        $pass = $this->pass();
        $foreign = Pass::factory()->registered()->create();
        Sanctum::actingAs($this->agent);

        $scans = [
            ['client_uuid' => (string) Str::uuid(), 'code' => $pass->token, 'result' => 'granted', 'direction' => 'out', 'scanned_at' => now()->subMinutes(5)->toIso8601String()],
            ['client_uuid' => (string) Str::uuid(), 'code' => $pass->token, 'result' => 'granted', 'direction' => 'in', 'scanned_at' => now()->subMinutes(10)->toIso8601String()],
            ['client_uuid' => (string) Str::uuid(), 'code' => $foreign->token, 'result' => 'granted', 'direction' => 'in', 'scanned_at' => now()->subMinutes(7)->toIso8601String()],
        ];

        $this->postJson('/api/v1/scans/batch', ['scans' => $scans])
            ->assertOk()
            ->assertJsonPath('created', 3);

        // Le dernier passage chronologique (sortie) détermine la position.
        $this->assertSame(Direction::Out, $pass->fresh()->presence);
        // Un pass hors des événements de l'agent est tracé sans être rattaché ni modifié.
        $this->assertSame(Direction::Out, $foreign->fresh()->presence);
        $this->assertDatabaseHas('scans', ['client_uuid' => $scans[2]['client_uuid'], 'pass_id' => null, 'event_id' => null, 'reason' => 'not_assigned']);

        $this->postJson('/api/v1/scans/batch', ['scans' => $scans])
            ->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('duplicates', 3);
    }

    public function test_sync_covers_all_my_events_and_only_them(): void
    {
        $this->pass();
        $this->pass('pending');
        Pass::factory()->registered()->create(); // autre événement
        Event::factory()->closed()->create()->staff()->attach($this->agent->id, ['role' => 'agent']);
        Sanctum::actingAs($this->agent);

        $this->travel(1)->minutes();
        $response = $this->getJson('/api/v1/sync')
            ->assertOk()
            ->assertJsonPath('full', true)
            ->assertJsonCount(1, 'events')
            ->assertJsonCount(2, 'passes');

        // Changements depuis : un pass modifié + tous les pass d'un événement nouvellement affecté.
        $this->travel(1)->minutes();
        $changed = $this->pass();
        $newType = PassType::factory()->create();
        $this->pass('pending', $newType);
        $this->travel(1)->minutes();
        $newType->event->staff()->attach($this->agent->id, ['role' => 'agent']);

        $this->getJson('/api/v1/sync?since='.urlencode($response->json('server_time')))
            ->assertOk()
            ->assertJsonPath('full', false)
            ->assertJsonCount(2, 'events')
            ->assertJsonCount(2, 'passes')
            ->assertJsonPath('passes.0.id', $changed->id);
    }

    public function test_closed_event_rejects_scans(): void
    {
        $pass = $this->pass();
        $this->event->update(['status' => 'closed']);
        Sanctum::actingAs($this->agent);

        $this->postJson('/api/v1/verify', ['code' => $pass->token])->assertJsonPath('reason', 'event_closed');
    }

    public function test_supervision_is_reserved_to_the_event_chief(): void
    {
        $this->pass();

        Sanctum::actingAs($this->agent);
        $this->getJson("/api/v1/events/{$this->event->id}/stats")->assertForbidden();
        $this->getJson("/api/v1/events/{$this->event->id}/scans")->assertForbidden();

        Sanctum::actingAs($this->chief);
        $this->getJson('/api/v1/events')->assertOk()->assertJsonPath('data.0.can_supervise', true);
        $this->getJson("/api/v1/events/{$this->event->id}/stats")->assertOk()->assertJsonPath('passes.registered', 1);
        $this->getJson('/api/v1/events/'.Event::factory()->create()->id.'/stats')->assertForbidden();
    }
}
