<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Pass;
use App\Models\PassType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Tests\TestCase;

class EventBrandingTest extends TestCase
{
    use RefreshDatabase;

    private array $event = ['name' => 'Gala', 'code' => 'GALA', 'starts_at' => '2026-12-01 18:00', 'ends_at' => '2026-12-01 23:00', 'status' => 'active'];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
    }

    /**
     * Affiche bleu nuit avec un bandeau orange.
     */
    private function poster(): UploadedFile
    {
        $image = new Imagick;
        $image->newImage(300, 420, new ImagickPixel('#0b1f4d'));
        $draw = new ImagickDraw;
        $draw->setFillColor('#f59e0b');
        $draw->rectangle(0, 280, 300, 420);
        $image->drawImage($draw);
        $image->setImageFormat('png');

        return UploadedFile::fake()->createWithContent('affiche.png', $image->getImageBlob());
    }

    public function test_logo_and_poster_are_stored_and_colors_extracted_from_poster(): void
    {
        $this->post(route('events.store'), [
            ...$this->event,
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'poster' => $this->poster(),
            'theme_from_poster' => 1,
        ])->assertSessionHasNoErrors();

        $event = Event::firstWhere('code', 'GALA');
        Storage::disk('public')->assertExists([$event->logo_path, $event->poster_path]);
        $this->assertSame('#f59e0b', $event->primary_color);
        $this->assertSame('#0b1f4d', $event->secondary_color);
    }

    public function test_manual_colors_are_kept_when_automatic_theme_is_off(): void
    {
        $this->post(route('events.store'), [
            ...$this->event,
            'poster' => $this->poster(),
            'theme_from_poster' => 0,
            'primary_color' => '#be123c',
            'secondary_color' => '#0f766e',
        ])->assertSessionHasNoErrors();

        $event = Event::firstWhere('code', 'GALA');
        $this->assertSame(['#be123c', '#0f766e'], [$event->primary_color, $event->secondary_color]);
    }

    public function test_replacing_or_removing_images_deletes_old_files(): void
    {
        $this->post(route('events.store'), [...$this->event, 'logo' => UploadedFile::fake()->image('a.png'), 'poster' => $this->poster(), 'theme_from_poster' => 1]);
        $event = Event::firstWhere('code', 'GALA');
        [$oldLogo, $oldPoster] = [$event->logo_path, $event->poster_path];

        $this->put(route('events.update', $event), [...$this->event, 'logo' => UploadedFile::fake()->image('b.png'), 'remove_poster' => 1, 'theme_from_poster' => 1])
            ->assertSessionHasNoErrors();

        $event->refresh();
        Storage::disk('public')->assertMissing([$oldLogo, $oldPoster]);
        Storage::disk('public')->assertExists($event->logo_path);
        $this->assertNull($event->poster_path);
    }

    public function test_images_are_validated(): void
    {
        $this->post(route('events.store'), [
            ...$this->event,
            'poster' => UploadedFile::fake()->create('affiche.pdf', 100, 'application/pdf'),
            'logo' => UploadedFile::fake()->image('logo.png')->size(config('parking.image_max_kb') + 1),
        ])->assertSessionHasErrors(['poster', 'logo']);
    }

    public function test_registration_page_uses_event_branding(): void
    {
        $this->post(route('events.store'), [...$this->event, 'logo' => UploadedFile::fake()->image('logo.png'), 'poster' => $this->poster(), 'theme_from_poster' => 1]);
        $event = Event::firstWhere('code', 'GALA');
        $pass = Pass::factory()->create(['pass_type_id' => PassType::factory()->create(['event_id' => $event->id])->id]);

        $this->get(route('public.pass', $pass->token))
            ->assertOk()
            ->assertSee('--brand: #f59e0b', false)
            ->assertSee($event->posterUrl(), false)
            ->assertSee($event->logoUrl(), false)
            ->assertSee('Rechercher une marque');
    }

    public function test_registration_page_has_a_default_theme_without_images(): void
    {
        $pass = Pass::factory()->create();

        $this->get(route('public.pass', $pass->token))->assertOk()->assertSee('--brand: #4f46e5', false);
    }
}
