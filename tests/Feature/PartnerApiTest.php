<?php

namespace Tests\Feature;

use App\Enums\Direction;
use App\Enums\PartnerAbility;
use App\Models\Event;
use App\Models\Partner;
use App\Models\Pass;
use App\Models\PassType;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerApiTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private PassType $type;

    private Partner $partner;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create();
        $this->type = PassType::factory()->create(['event_id' => $this->event->id]);
        $this->partner = Partner::factory()->create();
        $this->partner->events()->attach($this->event);
        [, $this->key] = $this->partner->issueApiKey('Production', PartnerAbility::cases());
    }

    private function pass(?PassType $type = null): Pass
    {
        return Pass::factory()->registered()->create(['pass_type_id' => ($type ?? $this->type)->id]);
    }

    private function api(?string $key = null): static
    {
        return $this->withToken($key ?? $this->key);
    }

    public function test_requests_without_valid_key_are_rejected(): void
    {
        $this->getJson('/api/partner/v1/events')->assertUnauthorized()->assertJsonPath('code', 'invalid_api_key');
        $this->api('ppk_inconnue')->getJson('/api/partner/v1/events')->assertUnauthorized();

        $this->partner->apiKeys()->first()->revoke();
        $this->api()->getJson('/api/partner/v1/events')->assertUnauthorized();
    }

    public function test_expired_key_and_inactive_partner_are_rejected(): void
    {
        [, $expired] = $this->partner->issueApiKey('Ancienne', PartnerAbility::cases(), now()->subDay());
        $this->api($expired)->getJson('/api/partner/v1/events')->assertUnauthorized();

        $this->partner->update(['is_active' => false]);
        $this->api()->getJson('/api/partner/v1/events')->assertUnauthorized();
    }

    public function test_key_accepted_in_x_api_key_header(): void
    {
        $this->withHeader('X-Api-Key', $this->key)->getJson('/api/partner/v1/events')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->event->id);
    }

    public function test_key_without_ability_is_forbidden(): void
    {
        [, $readOnly] = $this->partner->issueApiKey('Lecture', [PartnerAbility::Read]);

        $this->api($readOnly)->postJson('/api/partner/v1/scans', ['code' => $this->pass()->token])
            ->assertForbidden()
            ->assertJsonPath('code', 'insufficient_scope');
        $this->api($readOnly)->getJson('/api/partner/v1/scans')->assertOk();
    }

    public function test_ip_allowlist_is_enforced(): void
    {
        [, $restricted] = $this->partner->issueApiKey('Bureau', PartnerAbility::cases(), allowedIps: ['10.0.0.0/8']);

        $this->api($restricted)->getJson('/api/partner/v1/events')->assertForbidden()->assertJsonPath('code', 'ip_not_allowed');
        $this->api($restricted)->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->getJson('/api/partner/v1/events')->assertOk();
    }

    public function test_events_list_only_granted_events(): void
    {
        Event::factory()->create();

        $this->api()->getJson('/api/partner/v1/events')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_verify_returns_vehicle_without_personal_data(): void
    {
        $pass = $this->pass();

        $this->api()->postJson('/api/partner/v1/verify', ['code' => $pass->url()])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('pass.number', $pass->number)
            ->assertJsonPath('pass.vehicle.plate', $pass->vehicle->plate)
            ->assertJsonPath('pass.event.id', $this->event->id)
            ->assertJsonMissingPath('pass.vehicle.phone')
            ->assertJsonMissingPath('pass.token');
    }

    public function test_pass_of_event_without_access_looks_unknown(): void
    {
        $foreign = $this->pass(PassType::factory()->create());

        $this->api()->postJson('/api/partner/v1/verify', ['code' => $foreign->token])
            ->assertOk()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('reason', 'unknown_pass')
            ->assertJsonPath('pass', null);

        $this->api()->postJson('/api/partner/v1/verify', ['plate' => $foreign->vehicle->plate])
            ->assertOk()
            ->assertJsonPath('reason', 'unknown_plate');
    }

    public function test_scan_records_passage_and_updates_presence(): void
    {
        $pass = $this->pass();

        $this->api()->postJson('/api/partner/v1/scans', ['code' => $pass->token, 'operator_ref' => 'AG-12', 'latitude' => 5.32, 'longitude' => -4.01])
            ->assertCreated()
            ->assertJsonPath('scan.direction', 'in')
            ->assertJsonPath('scan.operator_ref', 'AG-12')
            ->assertJsonPath('pass.presence', 'in');

        $scan = Scan::sole();
        $this->assertSame($this->partner->id, $scan->partner_id);
        $this->assertNull($scan->user_id);
        $this->assertSame(Direction::In, $pass->fresh()->presence);
    }

    public function test_idempotency_key_returns_the_same_passage(): void
    {
        $pass = $this->pass();
        $headers = ['Idempotency-Key' => 'scan-42'];

        $first = $this->api()->postJson('/api/partner/v1/scans', ['code' => $pass->token], $headers)->assertCreated();
        $this->travel(1)->minutes();
        $this->api()->postJson('/api/partner/v1/scans', ['code' => $pass->token], $headers)
            ->assertOk()
            ->assertJsonPath('scan.id', $first->json('scan.id'));

        $this->assertSame(1, Scan::count());
        $this->assertSame(Direction::In, $pass->fresh()->presence);
    }

    public function test_partner_cannot_force_an_invalid_pass(): void
    {
        $revoked = Pass::factory()->registered()->revoked()->create(['pass_type_id' => $this->type->id]);

        $this->api()->postJson('/api/partner/v1/scans', ['code' => $revoked->token, 'force' => true])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'revoked');

        $this->api()->postJson('/api/partner/v1/scans', ['code' => $revoked->token, 'result' => 'denied'])->assertCreated();
        $this->assertSame(1, Scan::count());
    }

    public function test_history_contains_only_partner_scans(): void
    {
        $pass = $this->pass();
        $this->api()->postJson('/api/partner/v1/scans', ['code' => $pass->token, 'operator_ref' => 'AG-1'])->assertCreated();
        $this->travel(1)->minutes();
        $this->api()->postJson('/api/partner/v1/scans', ['code' => $pass->token, 'operator_ref' => 'AG-2'])->assertCreated();

        $agent = User::factory()->admin()->create();
        Sanctum::actingAs($agent);
        $this->postJson('/api/v1/scans', ['code' => $pass->token, 'result' => 'denied'])->assertCreated();

        $other = Partner::factory()->create();
        $other->events()->attach($this->event);
        [, $otherKey] = $other->issueApiKey('Autre', PartnerAbility::cases());
        $otherScan = $this->api($otherKey)->postJson('/api/partner/v1/scans', ['code' => $pass->token, 'result' => 'denied'])->json('scan.id');

        $this->api()->getJson('/api/partner/v1/scans')
            ->assertOk()
            ->assertJsonPath('summary', ['total' => 2, 'entries' => 1, 'exits' => 1, 'denied' => 0])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.operator_ref', 'AG-2')
            ->assertJsonPath('data.0.direction', 'out');

        $this->api()->getJson('/api/partner/v1/scans?operator_ref=AG-1')->assertJsonCount(1, 'data');
        $this->api()->getJson("/api/partner/v1/scans/{$otherScan}")->assertNotFound();
    }

    public function test_rate_limit_applies_per_key(): void
    {
        config(['parking.partner_rate_limit' => 2]);
        [, $second] = $this->partner->issueApiKey('Secours', PartnerAbility::cases());

        $this->api()->getJson('/api/partner/v1/events')->assertOk();
        $this->api()->getJson('/api/partner/v1/events')->assertOk();
        $this->api()->getJson('/api/partner/v1/events')->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
        $this->api($second)->getJson('/api/partner/v1/events')->assertOk();
    }

    public function test_partner_tokens_do_not_open_the_mobile_api(): void
    {
        $this->api()->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
