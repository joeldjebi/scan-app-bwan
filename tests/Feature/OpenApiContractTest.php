<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\Pass;
use App\Models\PassType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Garantit que docs/openapi.yaml (Swagger de l'équipe mobile) reflète exactement l'API réelle.
 */
class OpenApiContractTest extends TestCase
{
    use RefreshDatabase;

    private const SPEC = __DIR__.'/../../docs/openapi.yaml';

    private Event $event;

    private PassType $type;

    private User $agent;

    private User $chief;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create();
        $this->type = PassType::factory()->create(['event_id' => $this->event->id]);
        $this->agent = User::factory()->create();
        $this->chief = User::factory()->create();
        $this->event->staff()->attach([
            $this->agent->id => ['role' => StaffRole::Agent->value],
            $this->chief->id => ['role' => StaffRole::Chief->value],
        ]);
    }

    public function test_every_api_route_is_documented_and_vice_versa(): void
    {
        $documented = collect(Yaml::parseFile(self::SPEC)['paths'])
            ->flatMap(fn (array $operations, string $path) => collect($operations)
                ->keys()
                ->reject(fn ($key) => $key === 'parameters')
                ->map(fn ($method) => strtoupper($method).' '.$path))
            ->sort()->values()->all();

        $actual = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/'))
            ->flatMap(fn ($route) => collect($route->methods())
                ->reject(fn ($method) => $method === 'HEAD')
                ->map(fn ($method) => $method.' '.rtrim(Str::after($route->uri(), 'api/v1'), '/')))
            ->sort()->values()->all();

        $this->assertSame($actual, $documented);
    }

    public function test_auth_endpoints_match_spec(): void
    {
        $body = ['phone' => $this->agent->phone, 'password' => 'password', 'device_name' => 'Pixel'];
        $this->contract('post', '/auth/login', $this->postJson('/api/v1/auth/login', $body), 200, $body);
        $body = ['phone' => $this->agent->phone, 'password' => 'wrong', 'device_name' => 'Pixel'];
        $this->contract('post', '/auth/login', $this->postJson('/api/v1/auth/login', $body), 422, $body);
        $this->contract('get', '/auth/me', $this->getJson('/api/v1/auth/me'), 401);

        Sanctum::actingAs($this->agent);
        $this->contract('get', '/auth/me', $this->getJson('/api/v1/auth/me'), 200);

        $this->agent->update(['is_active' => false]);
        $this->contract('get', '/auth/me', $this->getJson('/api/v1/auth/me'), 403);
    }

    public function test_logout_matches_spec(): void
    {
        $token = $this->agent->createToken('Pixel')->plainTextToken;

        $this->contract('post', '/auth/logout', $this->withToken($token)->postJson('/api/v1/auth/logout'), 200);
    }

    public function test_verify_responses_match_spec(): void
    {
        $codes = [
            $this->pass('registered')->url(),
            $this->pass('pending')->token,
            $this->pass('revoked')->token,
            'inconnu',
            Pass::factory()->registered()->create()->token, // événement non affecté
        ];

        foreach ([$this->agent, $this->chief] as $user) {
            Sanctum::actingAs($user);

            foreach ($codes as $code) {
                $this->contract('post', '/verify', $this->postJson('/api/v1/verify', ['code' => $code]), 200, ['code' => $code]);
            }
        }

        // Saisie manuelle de l'immatriculation : plaque connue, inconnue, présente sur 2 événements.
        Sanctum::actingAs($this->agent);
        $plate = $this->pass('registered')->vehicle->plate;
        $twin = PassType::factory()->create();
        $twin->event->staff()->attach($this->agent->id, ['role' => StaffRole::Agent->value]);
        $duplicate = Pass::factory()->registered(['plate' => 'AA 111 BB'])->create(['pass_type_id' => $twin->id]);
        Pass::factory()->registered(['plate' => 'AA-111-BB'])->create(['pass_type_id' => $this->type->id]);

        foreach ([$plate, 'ZZ 999 ZZ', 'aa111bb'] as $value) {
            $this->contract('post', '/verify', $this->postJson('/api/v1/verify', ['plate' => $value]), 200, ['plate' => $value]);
        }
        $this->contract('post', '/scans', $this->postJson('/api/v1/scans', ['plate' => 'aa111bb']), 422, ['plate' => 'aa111bb']);
        $this->contract('post', '/scans', $this->postJson('/api/v1/scans', ['plate' => $plate]), 201, ['plate' => $plate]);

        $this->contract('post', '/verify', $this->postJson('/api/v1/verify', []), 422);
    }

    public function test_scan_responses_match_spec(): void
    {
        $pass = $this->pass('registered');
        $pending = $this->pass('pending');
        $uuid = (string) Str::uuid();
        Sanctum::actingAs($this->agent);

        $body = ['code' => $pass->url(), 'client_uuid' => $uuid, 'device_id' => 'A54', 'latitude' => 5.3197012, 'longitude' => -4.0167293, 'accuracy' => 12];
        $this->contract('post', '/scans', $this->postJson('/api/v1/scans', $body), 201, $body);
        $this->contract('post', '/scans', $this->postJson('/api/v1/scans', $body), 200, $body);

        $body = ['code' => $pass->token, 'direction' => 'in', 'result' => 'denied', 'reason' => 'Plaque différente'];
        $this->contract('post', '/scans', $this->postJson('/api/v1/scans', $body), 201, $body);

        $body = ['code' => 'inconnu', 'result' => 'denied', 'direction' => null];
        $this->contract('post', '/scans', $this->postJson('/api/v1/scans', $body), 201, $body);

        $body = ['code' => $pending->token];
        $this->contract('post', '/scans', $this->postJson('/api/v1/scans', $body), 422, $body);
        $this->contract('post', '/scans', $this->postJson('/api/v1/scans', ['code' => 'x', 'result' => 'maybe']), 422);

        Sanctum::actingAs($this->chief);
        $body = ['code' => $pending->token, 'force' => true, 'reason' => 'Invité'];
        $this->contract('post', '/scans', $this->postJson('/api/v1/scans', $body), 201, $body);

        Sanctum::actingAs($this->agent);
        $this->contract('get', '/scans/history', $this->getJson('/api/v1/scans/history'), 200);
    }

    public function test_offline_endpoints_match_spec(): void
    {
        $pass = $this->pass('registered');
        $this->pass('pending');
        Sanctum::actingAs($this->agent);

        $sync = $this->getJson('/api/v1/sync');
        $this->contract('get', '/sync', $sync, 200);
        $this->contract('get', '/sync', $this->getJson('/api/v1/sync?since='.urlencode($sync->json('server_time'))), 200);
        $this->contract('get', '/sync', $this->getJson('/api/v1/sync?since=pas-une-date'), 422);

        $body = ['scans' => [
            ['client_uuid' => (string) Str::uuid(), 'code' => $pass->token, 'result' => 'granted', 'direction' => 'in', 'scanned_at' => now()->subMinutes(3)->toIso8601String(), 'device_id' => 'A54', 'latitude' => 5.32, 'longitude' => -4.01, 'accuracy' => 8.5],
            ['client_uuid' => (string) Str::uuid(), 'code' => 'inconnu', 'result' => 'denied', 'direction' => 'in', 'reason' => 'QR illisible', 'scanned_at' => now()->subMinute()->toIso8601String()],
        ]];
        $this->contract('post', '/scans/batch', $this->postJson('/api/v1/scans/batch', $body), 200, $body);
        $this->contract('post', '/scans/batch', $this->postJson('/api/v1/scans/batch', ['scans' => [['code' => 'x']]]), 422);
    }

    public function test_supervision_endpoints_match_spec(): void
    {
        $pass = $this->pass('registered');
        Sanctum::actingAs($this->agent);
        $this->postJson('/api/v1/scans', ['code' => $pass->token]);
        $this->contract('get', '/events', $this->getJson('/api/v1/events'), 200);
        $this->contract('get', '/events/{event}/stats', $this->getJson($this->url('stats')), 403);

        Sanctum::actingAs($this->chief);
        $this->contract('get', '/events', $this->getJson('/api/v1/events'), 200);
        $this->contract('get', '/events/{event}/stats', $this->getJson($this->url('stats')), 200);
        $this->contract('get', '/events/{event}/scans', $this->getJson($this->url('scans')), 200);
        $this->contract('get', '/events/{event}/stats', $this->getJson('/api/v1/events/999/stats'), 404);
    }

    public function test_swagger_ui_is_served(): void
    {
        $this->get(route('api-docs'))->assertOk()->assertSee('swagger-ui');
        $this->get(route('api-docs.spec'))->assertOk()->assertSee('openapi: 3.0.3');

        config(['parking.api_docs' => false]);
        $this->get(route('api-docs'))->assertNotFound();
    }

    private function pass(string $state): Pass
    {
        $factory = Pass::factory()->state(['pass_type_id' => $this->type->id]);

        return match ($state) {
            'registered' => $factory->registered()->create(),
            'revoked' => $factory->registered()->revoked()->create(),
            default => $factory->create(),
        };
    }

    private function url(string $path): string
    {
        return rtrim("/api/v1/events/{$this->event->id}/{$path}", '/');
    }

    /**
     * Vérifie le code HTTP puis valide la réponse (et le corps de la requête) contre le schéma Swagger.
     */
    private function contract(string $method, string $path, TestResponse $response, int $status, ?array $requestBody = null): void
    {
        $response->assertStatus($status);

        $builder = (new ValidatorBuilder)->fromYamlFile(self::SPEC);
        $address = new OperationAddress($path, $method);
        $factory = new Psr17Factory;
        $psr = new PsrHttpFactory($factory, $factory, $factory, $factory);

        $builder->getResponseValidator()->validate($address, $psr->createResponse($response->baseResponse));

        if ($requestBody !== null) {
            $request = $factory->createServerRequest(strtoupper($method), '/api/v1'.str_replace('{event}', (string) $this->event->id, $path))
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Authorization', 'Bearer 1|token')
                ->withBody($factory->createStream(json_encode($requestBody)));

            $builder->getRoutedRequestValidator()->validate($address, $request);
        }

        $this->addToAssertionCount(1);
    }
}
