<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Pass;
use App\Models\PassType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCodeTest extends TestCase
{
    use RefreshDatabase;

    private array $event = ['name' => 'Afro Summer Festival', 'starts_at' => '2026-12-01 18:00', 'ends_at' => '2026-12-01 23:00', 'status' => 'active'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_code_is_generated_from_name_and_year_when_left_empty(): void
    {
        $this->post(route('events.store'), [...$this->event, 'code' => ''])->assertSessionHasNoErrors();
        $this->post(route('events.store'), [...$this->event, 'code' => ''])->assertSessionHasNoErrors();

        $this->assertSame(['ASF26', 'ASF26B'], Event::orderBy('id')->pluck('code')->all());
    }

    public function test_suggestion_endpoint_follows_the_name(): void
    {
        $this->getJson(route('events.code-suggestion', ['name' => 'Gala de la Saint-Valentin', 'starts_at' => '2027-02-14T20:00']))
            ->assertOk()
            ->assertJsonPath('code', 'GSV27');
    }

    public function test_admin_can_still_choose_a_custom_code(): void
    {
        $this->post(route('events.store'), [...$this->event, 'code' => 'afro2026'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', ['code' => 'AFRO2026']);
    }

    public function test_code_is_locked_once_passes_exist(): void
    {
        $type = PassType::factory()->create();
        $event = $type->event;
        Pass::factory()->create(['pass_type_id' => $type->id]);

        $this->put(route('events.update', $event), [...$this->event, 'code' => 'NOUVEAU'])->assertSessionHasErrors('code');
        $this->get(route('events.edit', $event))->assertSee('Verrouillé');
    }
}
