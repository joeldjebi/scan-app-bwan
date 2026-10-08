<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_logs_into_the_app_with_phone_in_any_format(): void
    {
        $agent = User::factory()->create(['phone' => '+225 07 01 02 03 04', 'email' => null]);
        $this->assertSame('+2250701020304', $agent->phone);

        foreach (['+2250701020304', '+225 07 01 02 03 04', '+225-07.01.02.03.04'] as $phone) {
            $this->postJson('/api/v1/auth/login', ['phone' => $phone, 'password' => 'password', 'device_name' => 'Pixel'])
                ->assertOk()
                ->assertJsonPath('user.id', $agent->id)
                ->assertJsonPath('user.phone', '+2250701020304');
        }
    }

    public function test_app_login_rejects_wrong_credentials_and_email(): void
    {
        $agent = User::factory()->create();

        $this->postJson('/api/v1/auth/login', ['phone' => $agent->phone, 'password' => 'wrong', 'device_name' => 'Pixel'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', 'Numéro de téléphone ou mot de passe incorrect.');

        $this->postJson('/api/v1/auth/login', ['email' => $agent->email, 'password' => 'password', 'device_name' => 'Pixel'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_back_office_accepts_phone_or_email(): void
    {
        $chief = User::factory()->create(['phone' => '+2250701010101', 'email' => null]);
        $admin = User::factory()->admin()->create(['phone' => null]);

        $this->post('/login', ['login' => '+225 07 01 01 01 01', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($chief);
        $this->post('/logout');

        $this->post('/login', ['login' => $admin->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_back_office_rejects_an_identifier_that_is_not_a_phone(): void
    {
        // Un identifiant sans chiffres ne doit jamais correspondre aux comptes sans téléphone.
        User::factory()->admin()->create(['phone' => null]);

        $this->post('/login', ['login' => 'abc', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_agent_account_requires_a_unique_phone_and_admin_an_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['phone' => '+2250700000001']);
        $this->actingAs($admin);

        $account = ['name' => 'Ali', 'role' => 'agent', 'password' => 'password123', 'password_confirmation' => 'password123', 'is_active' => 1];

        $this->post(route('users.store'), $account)->assertSessionHasErrors('phone');
        $this->post(route('users.store'), [...$account, 'phone' => '+225 07 00 00 00 01'])->assertSessionHasErrors('phone');
        $this->post(route('users.store'), [...$account, 'role' => 'admin'])->assertSessionHasErrors('email');

        $this->post(route('users.store'), [...$account, 'phone' => '07 00 00 00 02'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['name' => 'Ali', 'phone' => '0700000002', 'email' => null]);
    }

    public function test_account_search_by_name_does_not_match_every_phone(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin']);
        User::factory()->create(['name' => 'Awa']);
        User::factory()->create(['name' => 'Moussa', 'phone' => '+2250755555555']);

        $this->actingAs($admin)->get(route('users.index', ['q' => 'Awa']))->assertSee('Awa')->assertDontSee('Moussa');
        $this->get(route('users.index', ['q' => '07 55 55']))->assertSee('Moussa')->assertDontSee('Awa');
    }
}
