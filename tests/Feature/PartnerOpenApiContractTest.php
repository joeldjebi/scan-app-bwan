<?php

namespace Tests\Feature;

use App\Enums\PartnerAbility;
use App\Models\Event;
use App\Models\Partner;
use App\Models\Pass;
use App\Models\PassType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Garantit que docs/openapi-partner.yaml (Swagger des partenaires) reflète exactement l'API partenaire.
 */
class PartnerOpenApiContractTest extends TestCase
{
    use RefreshDatabase;

    private const SPEC = __DIR__.'/../../docs/openapi-partner.yaml';

    private PassType $type;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $event = Event::factory()->create(['location' => 'Palais de la Culture']);
        $this->type = PassType::factory()->create(['event_id' => $event->id]);
        $partner = Partner::factory()->create();
        $partner->events()->attach($event);
        [, $this->key] = $partner->issueApiKey('Production', PartnerAbility::cases());
        $this->withToken($this->key);
    }

    public function test_every_partner_route_is_documented_and_vice_versa(): void
    {
        $documented = collect(Yaml::parseFile(self::SPEC)['paths'])
            ->flatMap(fn (array $operations, string $path) => collect($operations)->keys()->map(fn ($method) => strtoupper($method).' '.$path))
            ->sort()->values()->all();

        $actual = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/partner/v1/'))
            ->flatMap(fn ($route) => collect($route->methods())
                ->reject(fn ($method) => $method === 'HEAD')
                ->map(fn ($method) => $method.' '.Str::after($route->uri(), 'api/partner/v1')))
            ->sort()->values()->all();

        $this->assertSame($actual, $documented);
    }

    public function test_responses_match_spec(): void
    {
        $pass = Pass::factory()->registered()->create(['pass_type_id' => $this->type->id]);
        $revoked = Pass::factory()->registered()->revoked()->create(['pass_type_id' => $this->type->id]);

        $this->contract('get', '/events', $this->getJson('/api/partner/v1/events'), 200);

        foreach ([['code' => $pass->url()], ['code' => 'inconnu'], ['plate' => $pass->vehicle->plate], ['code' => $revoked->token]] as $body) {
            $this->contract('post', '/verify', $this->postJson('/api/partner/v1/verify', $body), 200, $body);
        }
        $this->contract('post', '/verify', $this->postJson('/api/partner/v1/verify', []), 422);

        $body = ['code' => $pass->token, 'operator_ref' => 'AG-7', 'device_id' => 'TAB-1', 'latitude' => 5.3197012, 'longitude' => -4.0167293, 'accuracy' => 12];
        $headers = ['Idempotency-Key' => (string) Str::uuid()];
        $created = $this->postJson('/api/partner/v1/scans', $body, $headers);
        $this->contract('post', '/scans', $created, 201, $body);
        $this->contract('post', '/scans', $this->postJson('/api/partner/v1/scans', $body, $headers), 200, $body);

        $body = ['code' => $revoked->token];
        $this->contract('post', '/scans', $this->postJson('/api/partner/v1/scans', $body), 422, $body);
        $body = ['code' => 'inconnu', 'result' => 'denied', 'reason' => 'QR illisible'];
        $this->contract('post', '/scans', $this->postJson('/api/partner/v1/scans', $body), 201, $body);

        $this->contract('get', '/scans', $this->getJson('/api/partner/v1/scans'), 200);
        $this->contract('get', '/scans/{scan}', $this->getJson('/api/partner/v1/scans/'.$created->json('scan.id')), 200);
        $this->contract('get', '/scans/{scan}', $this->getJson('/api/partner/v1/scans/999999'), 404);

        $this->withToken('ppk_invalide');
        $this->contract('get', '/events', $this->getJson('/api/partner/v1/events'), 401);
    }

    public function test_partner_swagger_ui_is_served(): void
    {
        $this->get(route('api-docs.partner'))->assertOk()->assertSee('API partenaires v1');
        $this->get(route('api-docs.partner.spec'))->assertOk()->assertSee('openapi: 3.0.3');
    }

    private function contract(string $method, string $path, TestResponse $response, int $status, ?array $requestBody = null): void
    {
        $response->assertStatus($status);

        $builder = (new ValidatorBuilder)->fromYamlFile(self::SPEC);
        $address = new OperationAddress($path, $method);
        $factory = new Psr17Factory;
        $psr = new PsrHttpFactory($factory, $factory, $factory, $factory);

        $builder->getResponseValidator()->validate($address, $psr->createResponse($response->baseResponse));

        if ($requestBody !== null) {
            $request = $factory->createServerRequest(strtoupper($method), '/api/partner/v1'.$path)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Authorization', 'Bearer ppk_test')
                ->withBody($factory->createStream(json_encode($requestBody)));

            $builder->getRoutedRequestValidator()->validate($address, $request);
        }

        $this->addToAssertionCount(1);
    }
}
