<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AjaxResponsesTest extends TestCase
{
    use RefreshDatabase;

    private array $ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_ajax_save_returns_message_and_page_to_reload(): void
    {
        $response = $this->withHeaders($this->ajax)->post(route('brands.store'), ['name' => 'Lada'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Marque « Lada » ajoutée.');

        $this->assertNotEmpty($response->json('redirect'));
        // Le message n'est pas réaffiché au chargement suivant.
        $this->get(route('brands.index'))->assertDontSee('Marque « Lada » ajoutée.');
    }

    public function test_ajax_validation_errors_are_returned_per_field(): void
    {
        $this->withHeaders($this->ajax)->post(route('brands.store'), ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_ajax_refusal_is_returned_as_error(): void
    {
        $event = Event::factory()->create();
        Scan::create(['event_id' => $event->id, 'direction' => 'in', 'result' => 'denied', 'scanned_at' => now()]);

        $this->withHeaders($this->ajax)->delete(route('events.destroy', $event))
            ->assertUnprocessable()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Impossible de supprimer un événement qui a déjà des passages. Clôturez-le plutôt.');
    }

    public function test_classic_form_submission_still_redirects(): void
    {
        $this->post(route('brands.store'), ['name' => 'Lada'])->assertRedirect()->assertSessionHas('success');
    }

    public function test_back_office_pages_load_the_shared_script_and_loader(): void
    {
        $this->get(route('dashboard'))
            ->assertSee('js/app.js', false)
            ->assertSee('id="app-loader"', false)
            ->assertSee('id="app-page"', false);
    }
}
