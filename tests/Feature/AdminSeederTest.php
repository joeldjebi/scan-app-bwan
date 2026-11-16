<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_owner_with_configured_credentials(): void
    {
        config(['parking.admin' => ['email' => 'boss@example.com', 'password' => 'Secret-123']]);

        $this->seed(DatabaseSeeder::class);

        $owner = User::where('is_owner', true)->sole();
        $this->assertSame('boss@example.com', $owner->email);
        $this->assertTrue($owner->isAdmin());
        $this->assertTrue(Hash::check('Secret-123', $owner->password));
    }

    public function test_seeder_updates_existing_owner_credentials_without_creating_a_new_admin(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'old@example.com', 'name' => 'Joël']);
        config(['parking.admin' => ['email' => 'new@example.com', 'password' => 'New-Pass-456']]);

        $this->seed(DatabaseSeeder::class);

        $owner->refresh();
        $this->assertSame('new@example.com', $owner->email);
        $this->assertSame('Joël', $owner->name);
        $this->assertTrue(Hash::check('New-Pass-456', $owner->password));
        $this->assertSame(1, User::count());
    }
}
