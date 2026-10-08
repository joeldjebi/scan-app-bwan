<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\Pass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Chaque action du back-office (admin / chef) doit se refléter immédiatement dans l'API mobile,
 * et inversement.
 */
class AdminApiCoherenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $chief;

    private User $agent;

    private Event $event;

    private Pass $pass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->chief = User::factory()->create();
        $this->agent = User::factory()->create();

        // Parcours complet côté admin : événement, type, génération, équipe.
        $this->actingAs($this->admin)->post(route('events.store'), [
            'name' => 'Gala', 'code' => 'GALA', 'starts_at' => '2026-12-01 18:00', 'ends_at' => '2026-12-01 23:00', 'status' => 'active',
        ]);
        $this->event = Event::firstWhere('code', 'GALA');
        $this->post(route('events.types.store', $this->event), ['name' => 'VIP', 'code' => 'VIP', 'color' => '#112233']);
        $this->post(route('events.types.generate', [$this->event, $this->event->passTypes()->first()]), ['count' => 3]);
        $this->post(route('events.staff.store', $this->event), ['user_ids' => [$this->chief->id], 'role' => 'chief']);
        $this->post(route('events.staff.store', $this->event), ['user_ids' => [$this->agent->id], 'role' => 'agent']);

        // L'usager enregistre son véhicule via le lien du QR code.
        $this->pass = $this->event->passes()->orderBy('id')->first();
        $this->post(route('public.pass.register', $this->pass->token), [
            'plate' => '1234 AB 01', 'brand' => 'Toyota', 'color' => 'Blanc', 'phone' => '0700000000',
        ]);
    }

    private function verifyAs(User $user, ?Pass $pass = null)
    {
        Sanctum::actingAs($user);

        return $this->postJson('/api/v1/verify', ['code' => ($pass ?? $this->pass)->url()]);
    }

    public function test_generated_and_registered_pass_is_valid_in_the_app(): void
    {
        $this->verifyAs($this->agent)
            ->assertJsonPath('valid', true)
            ->assertJsonPath('event.name', 'Gala')
            ->assertJsonPath('pass.number', 'GALA-VIP-0001')
            ->assertJsonPath('pass.type.color', '#112233')
            ->assertJsonPath('pass.vehicle.plate', '1234 AB 01');

        // Les pass non enregistrés de l'événement sont bien reconnus mais refusés.
        $this->verifyAs($this->agent, $this->event->passes()->orderByDesc('id')->first())
            ->assertJsonPath('reason', 'not_registered');
    }

    public function test_revocation_and_restore_from_back_office(): void
    {
        $this->actingAs($this->chief)->post(route('events.passes.revoke', [$this->event, $this->pass]))->assertRedirect();

        $this->verifyAs($this->agent)->assertJsonPath('reason', 'revoked')->assertJsonPath('can_force', false);
        $this->verifyAs($this->chief)->assertJsonPath('reason', 'revoked')->assertJsonPath('can_force', true);

        $this->actingAs($this->admin)->post(route('events.passes.restore', [$this->event, $this->pass]));
        $this->verifyAs($this->agent)->assertJsonPath('valid', true);
    }

    public function test_vehicle_change_in_back_office_reaches_app_and_offline_sync(): void
    {
        $this->travel(1)->minutes();
        Sanctum::actingAs($this->agent);
        $since = $this->getJson('/api/v1/sync')->json('server_time');
        $this->travel(1)->minutes();

        $this->actingAs($this->chief)->put(route('events.passes.vehicle', [$this->event, $this->pass]), [
            'plate' => '9999 ZZ 01', 'brand' => 'Autre', 'brand_other' => 'Lada', 'color' => 'Rouge', 'phone' => '0711111111',
        ])->assertSessionHasNoErrors();

        $this->verifyAs($this->agent)->assertJsonPath('pass.vehicle.plate', '9999 ZZ 01')->assertJsonPath('pass.vehicle.brand', 'Lada');

        $this->getJson('/api/v1/sync?since='.urlencode($since))
            ->assertJsonCount(1, 'passes')
            ->assertJsonPath('passes.0.vehicle.plate', '9999 ZZ 01');
    }

    public function test_vehicle_reset_reopens_public_registration(): void
    {
        $this->actingAs($this->admin)->post(route('events.passes.reset', [$this->event, $this->pass]));

        $this->verifyAs($this->agent)->assertJsonPath('reason', 'not_registered');
        $this->get(route('public.pass', $this->pass->token))->assertSee('Enregistrer mon véhicule');
    }

    public function test_staff_changes_apply_to_the_app(): void
    {
        // Retrait de l'agent : plus aucun droit sur les pass de l'événement.
        $this->actingAs($this->admin)->delete(route('events.staff.destroy', [$this->event, $this->agent]));
        $this->verifyAs($this->agent)->assertJsonPath('reason', 'not_assigned')->assertJsonPath('pass', null);
        $this->getJson('/api/v1/sync')->assertJsonCount(0, 'events')->assertJsonCount(0, 'passes');

        // Promotion d'un nouveau chef : l'ancien perd la supervision.
        $newChief = User::factory()->create();
        $this->actingAs($this->admin)->post(route('events.staff.store', $this->event), ['user_ids' => [$newChief->id], 'role' => 'chief']);

        Sanctum::actingAs($newChief);
        $this->getJson("/api/v1/events/{$this->event->id}/stats")->assertOk();
        Sanctum::actingAs($this->chief);
        $this->getJson("/api/v1/events/{$this->event->id}/stats")->assertForbidden();
        $this->verifyAs($this->chief)->assertJsonPath('valid', true); // reste agent de l'événement
    }

    public function test_closing_event_blocks_everything_without_exception(): void
    {
        $this->actingAs($this->admin)->put(route('events.update', $this->event), [
            'name' => 'Gala', 'code' => 'GALA', 'starts_at' => '2026-12-01 18:00', 'ends_at' => '2026-12-01 23:00', 'status' => 'closed',
        ])->assertSessionHasNoErrors();

        $this->verifyAs($this->chief)->assertJsonPath('reason', 'event_closed')->assertJsonPath('can_force', false);
        $this->postJson('/api/v1/scans', ['code' => $this->pass->token, 'force' => true])->assertUnprocessable();
        $this->getJson('/api/v1/events')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/sync')->assertJsonCount(0, 'passes');

        $pending = $this->event->passes()->orderByDesc('id')->first();
        $this->post(route('public.pass.register', $pending->token), [
            'plate' => '5555 CC 01', 'brand' => 'Kia', 'color' => 'Noir', 'phone' => '0722222222',
        ])->assertForbidden();
    }

    public function test_deactivated_account_is_cut_off_from_the_app(): void
    {
        $token = $this->agent->createToken('phone')->plainTextToken;

        $this->actingAs($this->admin)->put(route('users.update', $this->agent), [
            'name' => $this->agent->name, 'phone' => $this->agent->phone, 'role' => 'agent', 'is_active' => '0',
        ])->assertRedirect(route('users.index'));

        // Tokens révoqués à la désactivation.
        $this->assertSame(0, $this->agent->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/verify', ['code' => 'x'])->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', ['phone' => $this->agent->phone, 'password' => 'password', 'device_name' => 'p'])
            ->assertUnprocessable();
    }

    public function test_deactivated_account_without_json_header_gets_403_not_500(): void
    {
        $this->agent->update(['is_active' => false]);
        Sanctum::actingAs($this->agent);

        $this->get('/api/v1/scans/history')->assertForbidden();
    }

    public function test_app_scans_are_reflected_in_back_office_and_stats_match(): void
    {
        Sanctum::actingAs($this->agent);
        $this->postJson('/api/v1/scans', ['code' => $this->pass->token])->assertCreated();
        $this->postJson('/api/v1/scans', ['code' => $this->event->passes()->orderByDesc('id')->first()->token, 'result' => 'denied']);

        Sanctum::actingAs($this->chief);
        $api = $this->getJson("/api/v1/events/{$this->event->id}/stats")
            ->assertJsonPath('passes.inside', 1)
            ->assertJsonPath('scans.entries', 1)
            ->assertJsonPath('scans.denied', 1);

        $this->actingAs($this->chief)->get(route('events.passes.index', [$this->event, 'presence' => 'in']))
            ->assertSee('GALA-VIP-0001')
            ->assertSee('Dans le parking');

        $this->get(route('events.scans.index', $this->event))
            ->assertSee($this->agent->name)
            ->assertSee('Aucun véhicule n&#039;est enregistré sur ce pass.', false);

        $page = $this->get(route('events.show', $this->event))->assertOk()->getContent();
        foreach (['Dans le parking' => $api->json('passes.inside'), 'Entrées' => $api->json('scans.entries'), 'Refus' => $api->json('scans.denied')] as $label => $value) {
            $this->assertMatchesRegularExpression('#'.$value.'</p>\s*<p class="text-xs text-slate-500">'.$label.'#', $page);
        }
    }

    public function test_chief_sees_same_scope_in_back_office_and_api(): void
    {
        $other = Event::factory()->create();
        $other->staff()->attach($this->chief->id, ['role' => StaffRole::Agent->value]);

        $this->actingAs($this->chief)->get(route('dashboard'))->assertSee('Gala')->assertDontSee($other->name);
        $this->get(route('events.show', $other))->assertForbidden();

        Sanctum::actingAs($this->chief);
        $this->getJson('/api/v1/events')->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/events/{$other->id}/stats")->assertForbidden();
        $this->getJson("/api/v1/events/{$this->event->id}/stats")->assertOk();
    }
}
