<?php

namespace Tests\Feature;

use App\Enums\PassStatus;
use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\Pass;
use App\Models\PassType;
use App\Models\User;
use App\Services\PassGenerator;
use App\Services\QrCodeExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackOfficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_event_type_and_generates_passes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('events.store'), [
            'name' => 'Gala', 'code' => 'gala26', 'starts_at' => '2026-12-01 18:00', 'ends_at' => '2026-12-01 23:00', 'status' => 'active',
        ])->assertRedirect();

        $event = Event::firstWhere('code', 'GALA26');
        $this->post(route('events.types.store', $event), ['name' => 'VIP', 'code' => 'vip', 'color' => '#112233'])->assertRedirect();
        $type = $event->passTypes()->first();

        $this->post(route('events.types.generate', [$event, $type]), ['count' => 25])->assertRedirect();

        $this->assertSame(25, $event->passes()->count());
        $this->get(route('events.show', $event))->assertOk()->assertSee('VIP');
        $this->get(route('events.passes.index', $event))->assertOk()->assertSee('GALA26-VIP-0001');
    }

    public function test_exports_qr_zip_and_spreadsheet(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $pass = Pass::factory()->registered()->create();

        $plan = $this->actingAs($admin)
            ->getJson(route('events.export.qrcodes.plan', [$pass->event_id, 'format' => 'svg', 'batch_size' => 0]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'lots');

        $response = $this->get($plan->json('lots.0.url'))->assertOk()->assertDownload();
        $zipPath = $response->baseResponse->getFile()->getPathname();

        $zip = new \ZipArchive;
        $zip->open($zipPath);
        $this->assertNotFalse($zip->getFromName("{$pass->type->code}/{$pass->number}.svg"));
        $this->assertStringContainsString($pass->url(), $zip->getFromName('passes.csv'));
        $zip->close();

        // Le ZIP n'est qu'un fichier temporaire supprimé après l'envoi.
        $this->assertTrue((fn () => $this->deleteFileAfterSend)->call($response->baseResponse));
        $this->assertStringStartsWith(realpath(sys_get_temp_dir()), realpath($zipPath));
        $this->assertSame([], Storage::disk('local')->allFiles('exports'));

        $this->get(route('events.export.passes', [$pass->event_id, 'format' => 'csv']))->assertOk();
    }

    public function test_qr_export_is_split_into_lots_generated_on_demand(): void
    {
        $admin = User::factory()->admin()->create();
        $type = PassType::factory()->create(['code' => 'VIP']);
        app(PassGenerator::class)->generate($type, 5);
        $code = $type->event->code;

        $exporter = app(QrCodeExporter::class);
        $lots = $exporter->plan($type->event, $type, 'png', 2);

        // Lots de 2 : 5 pass → 3 fichiers (2 + 2 + 1).
        $this->assertSame([2, 2, 1], array_column($lots, 'count'));
        $this->assertSame(["{$code}-VIP-0003", "{$code}-VIP-0004"], [$lots[1]['from'], $lots[1]['to']]);
        $this->assertStringEndsWith('-vip-png-lot-02-3-4.zip', $lots[1]['name']);

        $zip = $exporter->build($type->event, $type, 'png', 2, 3);
        $archive = new \ZipArchive;
        $archive->open($zip['path']);
        $image = imagecreatefromstring($archive->getFromName("VIP/{$code}-VIP-0005.png"));
        $archive->close();
        unlink($zip['path']);

        $this->assertNotFalse($image);
        $this->assertGreaterThan(900, imagesx($image));
        $this->assertSame(imagesx($image), imagesy($image));
        $this->assertNull($exporter->build($type->event, $type, 'png', 2, 9));

        $this->actingAs($admin)
            ->getJson(route('events.export.qrcodes.plan', [$type->event_id, 'format' => 'png', 'type' => $type->id, 'batch_size' => 250]))
            ->assertJsonCount(1, 'lots')
            ->assertJsonPath('lots.0.count', 5);
        $this->get(route('events.export.qrcodes', [$type->event_id, 'format' => 'png', 'batch_size' => 250, 'lot' => 9]))->assertNotFound();
        $this->getJson(route('events.export.qrcodes.plan', [$type->event_id, 'format' => 'gif']))->assertJsonValidationErrors('format');
    }

    public function test_only_one_chief_per_event(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();
        [$first, $second] = User::factory()->count(2)->create();

        $this->actingAs($admin)->post(route('events.staff.store', $event), ['user_ids' => [$first->id], 'role' => 'chief']);
        $this->post(route('events.staff.store', $event), ['user_ids' => [$second->id], 'role' => 'chief']);

        $this->assertSame(StaffRole::Agent, $first->staffRoleFor($event));
        $this->assertSame(StaffRole::Chief, $second->staffRoleFor($event));
    }

    public function test_chief_supervises_own_event_only(): void
    {
        $chief = User::factory()->create();
        $type = PassType::factory()->create();
        $event = $type->event;
        $event->staff()->attach($chief->id, ['role' => 'chief']);
        $pass = Pass::factory()->registered()->create(['pass_type_id' => $type->id]);

        $this->actingAs($chief)->get(route('events.show', $event))->assertOk();
        $this->post(route('events.passes.revoke', [$event, $pass]))->assertRedirect();
        $this->assertSame(PassStatus::Revoked, $pass->fresh()->status);

        // Pas d'accès aux fonctions d'administration ni aux autres événements.
        $this->get(route('events.create'))->assertForbidden();
        $this->get(route('events.export.qrcodes', [$event, 'format' => 'svg']))->assertForbidden();
        $this->get(route('events.show', Event::factory()->create()))->assertForbidden();
    }

    public function test_simple_agent_has_no_back_office_access_to_event(): void
    {
        $agent = User::factory()->create();
        $event = Event::factory()->create();
        $event->staff()->attach($agent->id, ['role' => 'agent']);

        $this->actingAs($agent)->get(route('events.show', $event))->assertForbidden();
    }

    public function test_pass_from_another_event_is_not_reachable(): void
    {
        $admin = User::factory()->admin()->create();
        $pass = Pass::factory()->create();
        $otherEvent = Event::factory()->create();

        $this->actingAs($admin)->get(route('events.passes.show', [$otherEvent, $pass]))->assertNotFound();
    }

    public function test_inactive_user_is_logged_out(): void
    {
        $user = User::factory()->admin()->inactive()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
