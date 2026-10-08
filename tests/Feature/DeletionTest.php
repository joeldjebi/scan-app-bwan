<?php

namespace Tests\Feature;

use App\Enums\Direction;
use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\Pass;
use App\Models\PassType;
use App\Models\User;
use App\Services\PassGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private Event $event;

    private PassType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->agent = User::factory()->create();
        $this->type = PassType::factory()->create(['code' => 'VIP']);
        $this->event = $this->type->event;
        $this->event->staff()->attach($this->agent->id, ['role' => StaffRole::Agent->value]);
    }

    private function registeredPass(array $vehicle = []): Pass
    {
        return Pass::factory()->registered($vehicle)->create(['pass_type_id' => $this->type->id]);
    }

    private function scan(Pass $pass): void
    {
        Sanctum::actingAs($this->agent);
        $this->postJson('/api/v1/scans', ['code' => $pass->token])->assertCreated();
    }

    public function test_deleting_a_pass_removes_its_vehicle_and_scans_and_disables_its_qr_code(): void
    {
        $pass = $this->registeredPass(['plate' => '1234 AB 01']);
        $this->scan($pass);

        $this->actingAs($this->admin)
            ->delete(route('events.passes.destroy', [$this->event, $pass]))
            ->assertRedirect(route('events.passes.index', $this->event));

        $this->assertSoftDeleted($pass);
        $this->assertDatabaseMissing('vehicles', ['pass_id' => $pass->id]);
        $this->assertDatabaseMissing('scans', ['pass_id' => $pass->id]);

        Sanctum::actingAs($this->agent);
        $this->postJson('/api/v1/verify', ['code' => $pass->url()])->assertJsonPath('reason', 'unknown_pass');
        $this->get(route('public.pass', $pass->token))->assertNotFound();

        // L'immatriculation est de nouveau disponible pour un autre pass de l'événement.
        $other = Pass::factory()->create(['pass_type_id' => $this->type->id]);
        $this->post(route('public.pass.register', $other->token), [
            'plate' => '1234 AB 01', 'brand' => 'Toyota', 'color' => 'Blanc', 'phone' => '0700000000',
        ])->assertSessionHasNoErrors();
    }

    public function test_offline_sync_reports_deleted_passes(): void
    {
        $pass = $this->registeredPass();
        $kept = $this->registeredPass();

        $this->travel(1)->minutes();
        Sanctum::actingAs($this->agent);
        $since = $this->getJson('/api/v1/sync')->assertJsonPath('deleted_pass_ids', [])->json('server_time');

        $this->travel(1)->minutes();
        $this->actingAs($this->admin)->delete(route('events.passes.destroy', [$this->event, $pass]));

        Sanctum::actingAs($this->agent);
        $this->getJson('/api/v1/sync?since='.urlencode($since))
            ->assertJsonPath('deleted_pass_ids', [$pass->id])
            ->assertJsonCount(0, 'passes');
        $this->getJson('/api/v1/sync')
            ->assertJsonCount(1, 'passes')
            ->assertJsonPath('passes.0.id', $kept->id);
    }

    public function test_numbering_never_reuses_a_deleted_pass_number(): void
    {
        $generator = app(PassGenerator::class);
        $generator->generate($this->type, 2);
        $last = $this->type->passes()->orderByDesc('sequence')->first();

        $this->actingAs($this->admin)->delete(route('events.passes.destroy', [$this->event, $last]));
        $generator->generate($this->type, 1);

        $this->assertSame(3, $this->type->passes()->max('sequence'));
    }

    public function test_bulk_delete_selected_passes_of_the_event_only(): void
    {
        [$first, $second, $kept] = Pass::factory()->count(3)->create(['pass_type_id' => $this->type->id]);
        $foreign = Pass::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('events.passes.bulk-destroy', $this->event), ['ids' => [$first->id, $second->id, $foreign->id]])
            ->assertSessionHas('success', '2 pass supprimé(s).');

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
        $this->assertNotSoftDeleted($kept);
        $this->assertNotSoftDeleted($foreign);
    }

    public function test_bulk_delete_all_passes_matching_filters(): void
    {
        $registered = $this->registeredPass();
        Pass::factory()->count(3)->create(['pass_type_id' => $this->type->id]);

        $this->actingAs($this->admin)
            ->delete(route('events.passes.bulk-destroy', $this->event), ['all_matching' => 1, 'status' => 'pending'])
            ->assertSessionHas('success', '3 pass supprimé(s).');

        $this->assertSame([$registered->id], $this->event->passes()->pluck('id')->all());
    }

    public function test_deleting_scans_recomputes_vehicle_presence(): void
    {
        $pass = $this->registeredPass();
        $this->scan($pass); // entrée
        $this->travel(1)->minutes();
        $this->scan($pass); // sortie
        [$exit, $entry] = [$pass->scans()->latest('id')->first(), $pass->scans()->oldest('id')->first()];

        $this->actingAs($this->admin)->delete(route('events.scans.destroy', [$this->event, $exit]))->assertRedirect();
        $this->assertSame(Direction::In, $pass->fresh()->presence);

        $this->delete(route('events.scans.bulk-destroy', $this->event), ['ids' => [$entry->id]])
            ->assertSessionHas('success', '1 passage(s) supprimé(s).');
        $this->assertSame(Direction::Out, $pass->fresh()->presence);
        $this->assertNull($pass->fresh()->last_scanned_at);
    }

    public function test_only_admin_can_delete(): void
    {
        $chief = User::factory()->create();
        $this->event->staff()->attach($chief->id, ['role' => StaffRole::Chief->value]);
        $pass = $this->registeredPass();
        $this->scan($pass);

        $this->actingAs($chief)->delete(route('events.passes.destroy', [$this->event, $pass]))->assertForbidden();
        $this->delete(route('events.passes.bulk-destroy', $this->event), ['ids' => [$pass->id]])->assertForbidden();
        $this->delete(route('events.scans.destroy', [$this->event, $pass->scans()->first()]))->assertForbidden();

        $this->assertNotSoftDeleted($pass);
        $this->assertSame(1, $pass->scans()->count());
    }

    public function test_cannot_delete_through_another_event(): void
    {
        $pass = $this->registeredPass();

        $this->actingAs($this->admin)
            ->delete(route('events.passes.destroy', [Event::factory()->create(), $pass]))
            ->assertNotFound();
    }

    public function test_admin_sees_delete_controls(): void
    {
        $pass = $this->registeredPass();
        $this->scan($pass);

        $this->actingAs($this->admin)->get(route('events.passes.index', $this->event))->assertSee('Supprimer la sélection');
        $this->get(route('events.passes.show', [$this->event, $pass]))->assertSee('Supprimer le pass');
        $this->get(route('events.scans.index', $this->event))->assertSee('Supprimer la sélection');
    }
}
