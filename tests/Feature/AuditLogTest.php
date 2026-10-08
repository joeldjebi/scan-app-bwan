<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Event;
use App\Models\Pass;
use App\Models\PassType;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create(['name' => 'Joël']);
    }

    private function lastLog(string $action): ?AuditLog
    {
        return AuditLog::where('action', $action)->latest('id')->first();
    }

    public function test_only_the_owner_sees_the_journal_and_attempts_are_logged(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Autre admin']);

        $this->actingAs($admin)->get(route('audit.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee('Journal');

        $denied = $this->lastLog('access.denied');
        $this->assertSame('Autre admin', $denied->actor_name);
        $this->assertStringContainsString('/audit', $denied->description);

        $this->actingAs($this->owner)->get(route('audit.index'))->assertOk()->assertSee('Accès refusé');
    }

    public function test_changes_are_logged_with_before_and_after_values_and_hidden_password(): void
    {
        $agent = User::factory()->create(['name' => 'Awa']);

        $this->actingAs($this->owner)->put(route('users.update', $agent), [
            'name' => 'Awa K.', 'phone' => $agent->phone, 'role' => 'agent', 'is_active' => 0,
            'password' => 'nouveau-secret', 'password_confirmation' => 'nouveau-secret',
        ])->assertRedirect();

        $log = $this->lastLog('user.updated');
        $this->assertSame('Compte « Awa K. » modifié', $log->description);
        $this->assertSame(['Awa', 'Awa K.'], $log->changes['name']);
        $this->assertSame([1, 0], array_map('intval', $log->changes['is_active']));
        $this->assertSame(['••••••••', '•••••••• (nouveau)'], $log->changes['password']);
        $this->assertStringNotContainsString('nouveau-secret', json_encode($log->toArray()));
        $this->assertSame('web', $log->channel);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertSame('Joël', $log->actor_name);
    }

    public function test_logins_are_logged_without_passwords(): void
    {
        $agent = User::factory()->create(['phone' => '+2250700000001']);

        $this->post('/login', ['login' => $this->owner->email, 'password' => 'mauvais']);
        $failed = $this->lastLog('auth.login_failed');
        $this->assertSame($this->owner->email, $failed->properties['identifiant']);
        $this->assertStringNotContainsString('mauvais', json_encode($failed->toArray()));

        $this->post('/login', ['login' => $this->owner->email, 'password' => 'password']);
        $this->assertSame('Joël', $this->lastLog('auth.login')->actor_name);

        $this->postJson('/api/v1/auth/login', ['phone' => '+225 07 00 00 00 01', 'password' => 'password', 'device_name' => 'Samsung A54'])->assertOk();
        $apiLogin = $this->lastLog('auth.login');
        $this->assertSame('api', $apiLogin->channel);
        $this->assertSame($agent->id, $apiLogin->user_id);
        $this->assertSame('Samsung A54', $apiLogin->properties['appareil']);
    }

    public function test_public_registration_and_pass_status_are_logged_but_not_presence(): void
    {
        $type = PassType::factory()->create();
        $pass = Pass::factory()->create(['pass_type_id' => $type->id]);

        $this->post(route('public.pass.register', $pass->token), [
            'plate' => '1234 AB 01', 'brand' => 'Toyota', 'color' => 'Blanc', 'phone' => '0700000000',
        ]);

        $vehicle = $this->lastLog('vehicle.created');
        $this->assertSame('public', $vehicle->channel);
        $this->assertSame($type->event_id, $vehicle->event_id);
        $this->assertSame('1234 AB 01', $vehicle->changes['plate'][1]);
        $this->assertSame(['pending', 'registered'], $this->lastLog('pass.updated')->changes['status']);

        // Un passage ne change que la position : il n'alimente pas le journal (il a son propre historique).
        $agent = User::factory()->create();
        $type->event->staff()->attach($agent->id, ['role' => StaffRole::Agent->value]);
        $before = AuditLog::count();
        Sanctum::actingAs($agent);
        $this->postJson('/api/v1/scans', ['code' => $pass->token])->assertCreated();
        $this->assertSame($before, AuditLog::count());
    }

    public function test_sensitive_operations_are_logged(): void
    {
        $type = PassType::factory()->create(['name' => 'VIP']);
        $event = $type->event;
        $chief = User::factory()->create(['name' => 'Koffi']);
        $this->actingAs($this->owner);

        $this->post(route('events.types.generate', [$event, $type]), ['count' => 3]);
        $this->assertSame(3, $this->lastLog('pass.generated')->properties['nombre']);

        $this->post(route('events.staff.store', $event), ['user_ids' => [$chief->id], 'role' => 'chief']);
        $this->assertStringContainsString('Koffi', $this->lastLog('staff.added')->description);

        $this->get(route('events.export.passes', [$event, 'format' => 'csv']));
        $this->assertSame($event->id, $this->lastLog('export.passes')->event_id);

        $numbers = $event->passes()->orderBy('id')->pluck('number')->all();
        $this->delete(route('events.passes.bulk-destroy', $event), ['all_matching' => 1]);
        $this->assertSame($numbers, $this->lastLog('pass.deleted')->properties['numeros']);

        // Passage forcé par le chef depuis l'application.
        $pending = Pass::factory()->create(['pass_type_id' => $type->id]);
        Sanctum::actingAs($chief);
        $this->postJson('/api/v1/scans', ['code' => $pending->token, 'force' => true, 'reason' => 'Invité'])->assertCreated();
        $forced = $this->lastLog('scan.forced');
        $this->assertSame('not_registered', $forced->properties['motif_refus']);
        $this->assertSame('Koffi', $forced->actor_name);
    }

    public function test_every_creation_deletion_and_export_is_logged(): void
    {
        $this->actingAs($this->owner);
        $logged = fn (string $action) => AuditLog::where('action', $action)->exists();

        // Création : événement, type, génération de pass (avec la plage de numéros), marque, compte
        $this->post(route('events.store'), ['name' => 'Gala', 'starts_at' => '2026-12-01 18:00', 'ends_at' => '2026-12-01 23:00', 'status' => 'active']);
        $event = Event::firstWhere('name', 'Gala');
        $this->post(route('events.types.store', $event), ['name' => 'VIP', 'code' => 'VIP', 'color' => '#112233']);
        $type = $event->passTypes()->first();
        $this->post(route('events.types.generate', [$event, $type]), ['count' => 3]);
        $this->post(route('brands.store'), ['name' => 'Lada']);
        $this->post(route('users.store'), ['name' => 'Ali', 'phone' => '0700000009', 'role' => 'agent', 'password' => 'password123', 'password_confirmation' => 'password123', 'is_active' => 1]);

        $generated = AuditLog::where('action', 'pass.generated')->firstOrFail();
        $this->assertSame([3, "{$event->code}-VIP-0001", "{$event->code}-VIP-0003"], [$generated->properties['nombre'], $generated->properties['du'], $generated->properties['au']]);
        foreach (['event.created', 'pass_type.created', 'brand.created', 'user.created'] as $action) {
            $this->assertTrue($logged($action), "Action non journalisée : {$action}");
        }

        // Exports : liste des pass, lot de QR codes, journal
        $this->get(route('events.export.passes', [$event, 'format' => 'xlsx']))->assertOk();
        $this->get(route('events.export.qrcodes', [$event, 'format' => 'svg', 'batch_size' => 250, 'lot' => 1]))->assertOk();
        $this->get(route('audit.export'))->assertOk();
        foreach (['export.passes', 'export.qrcodes', 'export.audit'] as $action) {
            $this->assertTrue($logged($action), "Export non journalisé : {$action}");
        }

        // Suppressions : passage, pass, marque, type vide, membre d'équipe, événement (avec ses pass)
        $pass = $event->passes()->first();
        $scan = Scan::create(['event_id' => $event->id, 'pass_id' => $pass->id, 'direction' => 'in', 'result' => 'granted', 'scanned_at' => now()]);
        $this->delete(route('events.scans.destroy', [$event, $scan]));
        $this->delete(route('events.passes.destroy', [$event, $pass]));
        $this->delete(route('brands.destroy', Brand::firstWhere('name', 'Lada')));
        $this->post(route('events.types.store', $event), ['name' => 'Presse', 'code' => 'PRS', 'color' => '#445566']);
        $this->delete(route('events.types.destroy', [$event, $event->passTypes()->firstWhere('code', 'PRS')]));
        $agent = User::firstWhere('name', 'Ali');
        $this->post(route('events.staff.store', $event), ['user_ids' => [$agent->id], 'role' => 'agent']);
        $this->delete(route('events.staff.destroy', [$event, $agent]));
        foreach (['scan.deleted', 'pass.deleted', 'brand.deleted', 'pass_type.deleted', 'staff.removed'] as $action) {
            $this->assertTrue($logged($action), "Suppression non journalisée : {$action}");
        }

        $this->delete(route('events.destroy', $event))->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertTrue($logged('event.deleted'), 'Événement supprimé non journalisé : '.json_encode(AuditLog::pluck('action')));
        $cascade = AuditLog::where('action', 'pass.deleted')->where('description', 'like', '%avec l\'événement%')->firstOrFail();
        $this->assertSame(3, $cascade->properties['nombre']);
    }

    public function test_entries_are_immutable(): void
    {
        $this->actingAs($this->owner)->post('/logout');
        $log = AuditLog::firstOrFail();

        $this->assertThrows(fn () => $log->update(['description' => 'falsifié']), LogicException::class);
        $this->assertThrows(fn () => $log->delete(), LogicException::class);
    }

    public function test_journal_filters_and_csv_export(): void
    {
        $this->post('/login', ['login' => 'inconnu@example.com', 'password' => 'x']);
        $event = Event::factory()->create(['name' => 'Gala']);
        $this->actingAs($this->owner);

        $this->get(route('audit.index', ['category' => 'event']))->assertSee('Événement « Gala » créé')->assertDontSee('inconnu@example.com');
        $this->get(route('audit.index', ['security' => 1]))->assertSee('inconnu@example.com');
        $this->get(route('audit.index', ['event' => $event->id]))->assertSee('Gala');

        $this->get(route('audit.export', ['category' => 'event']))->assertOk()->assertDownload();
    }
}
