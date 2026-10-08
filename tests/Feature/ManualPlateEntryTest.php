<?php

namespace Tests\Feature;

use App\Enums\Direction;
use App\Enums\ScanMethod;
use App\Enums\StaffRole;
use App\Models\Pass;
use App\Models\PassType;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManualPlateEntryTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private PassType $type;

    private Pass $pass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = PassType::factory()->create();
        $this->agent = User::factory()->create();
        $this->type->event->staff()->attach($this->agent->id, ['role' => StaffRole::Agent->value]);
        $this->pass = Pass::factory()->registered(['plate' => '1234 AB 01'])->create(['pass_type_id' => $this->type->id]);
        Sanctum::actingAs($this->agent);
    }

    public function test_plate_is_found_whatever_the_format(): void
    {
        foreach (['1234 AB 01', '1234ab01', '1234-ab-01', ' 1234.AB.01 '] as $plate) {
            $this->postJson('/api/v1/verify', ['plate' => $plate])
                ->assertOk()
                ->assertJsonPath('valid', true)
                ->assertJsonPath('method', 'plate')
                ->assertJsonPath('pass.id', $this->pass->id)
                ->assertJsonPath('pass.vehicle.plate', '1234 AB 01')
                ->assertJsonPath('pass.vehicle.plate_key', '1234AB01');
        }
    }

    public function test_validating_by_plate_is_recorded_as_manual_entry(): void
    {
        $this->postJson('/api/v1/scans', ['plate' => '1234-ab-01'])
            ->assertCreated()
            ->assertJsonPath('scan.method', 'plate')
            ->assertJsonPath('scan.direction', 'in');

        $this->assertSame(ScanMethod::Plate, Scan::firstOrFail()->method);
        $this->assertSame(Direction::In, $this->pass->fresh()->presence);

        $this->travel(1)->minutes();
        $this->postJson('/api/v1/scans', ['code' => $this->pass->token])->assertJsonPath('scan.method', 'qr');
    }

    public function test_unknown_plate_and_plate_of_another_event_are_not_revealed(): void
    {
        Pass::factory()->registered(['plate' => '9999 ZZ 99'])->create(); // autre événement

        foreach (['0000 XX 00', '9999 ZZ 99'] as $plate) {
            $this->postJson('/api/v1/verify', ['plate' => $plate])
                ->assertJsonPath('valid', false)
                ->assertJsonPath('reason', 'unknown_plate')
                ->assertJsonPath('pass', null);
        }

        $this->postJson('/api/v1/scans', ['plate' => '9999 ZZ 99'])->assertUnprocessable()->assertJsonPath('reason', 'unknown_plate');
    }

    public function test_plate_registered_on_several_of_my_events_asks_to_choose(): void
    {
        $otherType = PassType::factory()->create();
        $otherType->event->staff()->attach($this->agent->id, ['role' => StaffRole::Agent->value]);
        $other = Pass::factory()->registered(['plate' => '1234AB01'])->create(['pass_type_id' => $otherType->id]);

        $this->postJson('/api/v1/verify', ['plate' => '1234 AB 01'])
            ->assertJsonPath('valid', false)
            ->assertJsonPath('reason', 'multiple_matches')
            ->assertJsonCount(2, 'matches');

        $this->postJson('/api/v1/scans', ['plate' => '1234 AB 01'])->assertUnprocessable()->assertJsonCount(2, 'matches');
        $this->assertSame(0, Scan::count());

        // L'app valide ensuite le pass choisi par son jeton.
        $this->postJson('/api/v1/scans', ['code' => $other->token])->assertCreated();
    }

    public function test_plate_sent_in_code_field_is_treated_as_manual_entry(): void
    {
        $this->postJson('/api/v1/verify', ['code' => '1234 ab 01'])
            ->assertJsonPath('valid', true)
            ->assertJsonPath('method', 'plate')
            ->assertJsonPath('pass.id', $this->pass->id);

        $this->postJson('/api/v1/scans', ['code' => '1234AB01'])
            ->assertCreated()
            ->assertJsonPath('scan.method', 'plate');

        // Ni QR code ni immatriculation connus : la réponse reste celle d'un QR code inconnu.
        $this->postJson('/api/v1/verify', ['code' => '0000 XX 00'])
            ->assertJsonPath('reason', 'unknown_pass')
            ->assertJsonPath('method', 'qr');
    }

    public function test_code_or_plate_is_required(): void
    {
        $this->postJson('/api/v1/verify', [])->assertJsonValidationErrors(['code', 'plate']);
    }

    public function test_offline_batch_accepts_plates(): void
    {
        $this->postJson('/api/v1/scans/batch', ['scans' => [[
            'client_uuid' => (string) Str::uuid(), 'plate' => '1234ab01', 'result' => 'granted',
            'direction' => 'in', 'scanned_at' => now()->toIso8601String(),
        ]]])->assertOk()->assertJsonPath('created', 1);

        $scan = Scan::firstOrFail();
        $this->assertSame(ScanMethod::Plate, $scan->method);
        $this->assertSame($this->pass->id, $scan->pass_id);
    }

    public function test_manual_entries_are_visible_in_back_office_and_stats(): void
    {
        $this->postJson('/api/v1/scans', ['plate' => '1234 AB 01']);
        $chief = User::factory()->create();
        $this->type->event->staff()->attach($chief->id, ['role' => StaffRole::Chief->value]);

        Sanctum::actingAs($chief);
        $this->getJson("/api/v1/events/{$this->type->event_id}/stats")->assertJsonPath('scans.manual', 1);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('events.scans.index', [$this->type->event_id, 'method' => 'plate']))->assertSee('saisie manuelle');
        $this->get(route('users.scans', $this->agent))
            ->assertViewHas('summary', fn (array $summary) => $summary['manual'] === 1);
    }
}
