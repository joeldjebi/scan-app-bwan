<?php

namespace Tests\Feature;

use App\Enums\Direction;
use App\Enums\PartnerAbility;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Partner;
use App\Models\PartnerApiKey;
use App\Models\Pass;
use App\Models\PassType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerBackOfficeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->event = Event::factory()->create();
    }

    public function test_admin_creates_partner_with_event_access(): void
    {
        $this->actingAs($this->admin)
            ->post(route('partners.store'), ['name' => 'Sécurité Plus', 'is_active' => '1', 'event_ids' => [$this->event->id]])
            ->assertRedirect();

        $partner = Partner::sole();
        $this->assertTrue($partner->canOperate($this->event));
        $this->assertSame($this->admin->id, $partner->events()->first()->pivot->granted_by);
        $this->assertTrue(AuditLog::where('action', 'partner.access_granted')->exists());
    }

    public function test_generated_key_is_shown_once_and_stored_hashed(): void
    {
        $partner = Partner::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('partners.keys.store', $partner), ['name' => 'Production', 'abilities' => ['scans:verify', 'scans:write'], 'allowed_ips' => '41.202.10.5, 10.0.0.0/8'])
            ->assertSessionHas('plain_key');

        $plain = $response->getSession()->get('plain_key');
        $key = PartnerApiKey::sole();
        $this->assertSame(hash('sha256', $plain), $key->key_hash);
        $this->assertSame(['41.202.10.5', '10.0.0.0/8'], $key->allowed_ips);
        $this->assertSame(['scans:verify', 'scans:write'], $key->abilities);

        $this->get(route('partners.show', $partner))->assertOk()->assertSee($plain);
        $this->get(route('partners.show', $partner))->assertOk()->assertDontSee($plain);
    }

    public function test_invalid_ip_is_rejected(): void
    {
        $partner = Partner::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('partners.keys.store', $partner), ['name' => 'Prod', 'abilities' => ['scans:read'], 'allowed_ips' => '999.1.1.1'])
            ->assertSessionHasErrors('allowed_ips');

        $this->assertSame(0, PartnerApiKey::count());
    }

    public function test_revoked_key_is_refused_by_the_api(): void
    {
        $partner = Partner::factory()->create();
        [$key, $plain] = $partner->issueApiKey('Prod', PartnerAbility::cases());

        $this->actingAs($this->admin)->delete(route('partners.keys.revoke', [$partner, $key]))->assertRedirect();

        $this->withToken($plain)->getJson('/api/partner/v1/events')->assertUnauthorized();
    }

    public function test_chief_grants_and_revokes_partner_access_on_his_event(): void
    {
        $chief = User::factory()->create();
        $this->event->staff()->attach($chief, ['role' => StaffRole::Chief->value]);
        $partner = Partner::factory()->create();

        $this->actingAs($chief)->get(route('events.show', $this->event))->assertOk()->assertSee($partner->name);
        $this->post(route('events.partners.store', $this->event), ['partner_id' => $partner->id])->assertRedirect();
        $this->assertTrue($partner->canOperate($this->event));

        $this->delete(route('events.partners.destroy', [$this->event, $partner]))->assertRedirect();
        $this->assertFalse($partner->canOperate($this->event));
    }

    public function test_agent_and_other_chief_cannot_manage_partners(): void
    {
        $agent = User::factory()->create();
        $this->event->staff()->attach($agent, ['role' => StaffRole::Agent->value]);
        $partner = Partner::factory()->create();

        $this->actingAs($agent)->post(route('events.partners.store', $this->event), ['partner_id' => $partner->id])->assertForbidden();
        $this->get(route('partners.index'))->assertForbidden();
        $this->assertFalse($partner->canOperate($this->event));
    }

    public function test_partner_pages_render(): void
    {
        $partner = Partner::factory()->withWebhook()->create();
        $partner->events()->attach($this->event);

        $this->actingAs($this->admin);
        $this->get(route('partners.index'))->assertOk()->assertSee($partner->name);
        $this->get(route('partners.create'))->assertOk();
        $this->get(route('partners.edit', $partner))->assertOk();
        $this->get(route('partners.show', $partner))->assertOk()->assertSee($this->event->name);
    }

    public function test_admin_sees_partner_scan_history_filtered_by_operator(): void
    {
        $partner = Partner::factory()->create();
        $partner->events()->attach($this->event);
        [, $key] = $partner->issueApiKey('Prod', PartnerAbility::cases());
        $type = PassType::factory()->create(['event_id' => $this->event->id]);
        $first = Pass::factory()->registered()->create(['pass_type_id' => $type->id]);
        $second = Pass::factory()->registered()->create(['pass_type_id' => $type->id]);
        $agentPass = Pass::factory()->registered()->create(['pass_type_id' => $type->id]);

        $this->withToken($key)->postJson('/api/partner/v1/scans', ['code' => $first->token, 'operator_ref' => 'AG-1'])->assertCreated();
        $this->withToken($key)->postJson('/api/partner/v1/scans', ['code' => $second->token, 'operator_ref' => 'AG-2'])->assertCreated();
        $this->actingAs($this->admin)->postJson('/api/v1/scans', ['code' => $agentPass->token])->assertCreated();

        $this->actingAs($this->admin)->get(route('partners.scans', $partner))
            ->assertOk()
            ->assertSee($first->number)
            ->assertSee($second->number)
            ->assertDontSee($agentPass->number);

        $this->get(route('partners.scans', [$partner, 'operator' => 'AG-1']))
            ->assertOk()
            ->assertSee($first->number)
            ->assertDontSee($second->number);
    }

    public function test_non_admin_cannot_see_partner_scan_history(): void
    {
        $partner = Partner::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('partners.scans', $partner))->assertForbidden();
    }

    public function test_admin_deletes_partner_keeping_its_scan_history(): void
    {
        $partner = Partner::factory()->create(['name' => 'Sécurité Plus']);
        $partner->events()->attach($this->event);
        [, $key] = $partner->issueApiKey('Prod', PartnerAbility::cases());
        $pass = Pass::factory()->registered()->create(['pass_type_id' => PassType::factory()->create(['event_id' => $this->event->id])->id]);
        $this->withToken($key)->postJson('/api/partner/v1/scans', ['code' => $pass->token, 'operator_ref' => 'AG-1'])->assertCreated();

        $this->actingAs($this->admin)->delete(route('partners.destroy', $partner))->assertRedirect(route('partners.index'));

        $this->assertSoftDeleted($partner);
        $this->assertNotNull($partner->apiKeys()->sole()->revoked_at);
        $this->assertSame(0, $partner->events()->count());
        $this->withToken($key)->getJson('/api/partner/v1/events')->assertUnauthorized();

        $this->get(route('partners.index'))->assertSee('supprimé');
        $this->get(route('partners.index'))->assertDontSee('Sécurité Plus');
        $this->get(route('partners.show', $partner))->assertNotFound();
        $this->get(route('events.scans.index', $this->event))->assertOk()->assertSee('Sécurité Plus (supprimé) · AG-1');
        $this->assertSame(Direction::In, $pass->fresh()->presence);
    }

    public function test_non_admin_cannot_delete_partner(): void
    {
        $partner = Partner::factory()->create();

        $this->actingAs(User::factory()->create())->delete(route('partners.destroy', $partner))->assertForbidden();
        $this->assertNotSoftDeleted($partner);
    }
}
