<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Partner;
use App\Models\Pass;
use App\Models\PassType;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\PartnerWebhooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PartnerWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Pass $pass;

    private Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create();
        $this->pass = Pass::factory()->registered()->create(['pass_type_id' => PassType::factory()->create(['event_id' => $this->event->id])->id]);
        $this->partner = Partner::factory()->withWebhook()->create();
        $this->partner->events()->attach($this->event);
    }

    public function test_revoking_a_pass_sends_a_signed_webhook_to_event_partners(): void
    {
        Http::fake(['partner.example.com/*' => Http::response(null, 204)]);
        Partner::factory()->withWebhook('https://other.example.com/hook')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('events.passes.revoke', [$this->event, $this->pass]))
            ->assertRedirect();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            [$timestamp] = sscanf($request->header(PartnerWebhooks::SIGNATURE_HEADER)[0], 't=%d');
            $expected = PartnerWebhooks::signature($this->partner->fresh()->webhook_secret, $request->body(), $timestamp);

            return $request->url() === 'https://partner.example.com/webhooks'
                && $request->header(PartnerWebhooks::SIGNATURE_HEADER)[0] === $expected
                && $request['type'] === PartnerWebhooks::PASS_REVOKED
                && $request['data']['pass']['number'] === $this->pass->number;
        });

        $this->assertSame(WebhookDelivery::DELIVERED, WebhookDelivery::sole()->status);
    }

    public function test_failed_delivery_is_kept_for_retry(): void
    {
        Http::fake(['*' => Http::response('Erreur', 500)]);

        try {
            app(PartnerWebhooks::class)->passStatusChanged($this->pass, PartnerWebhooks::PASS_RESTORED);
        } catch (\RuntimeException) {
            // File synchrone en test : l'échec remonte, il serait réessayé en production.
        }

        $delivery = WebhookDelivery::sole();
        $this->assertSame(500, $delivery->response_status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNull($delivery->delivered_at);
    }
}
