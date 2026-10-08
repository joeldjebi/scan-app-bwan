<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Event $gala;

    private Event $forum;

    private array $account = ['name' => 'Awa', 'role' => 'agent', 'password' => 'password123', 'password_confirmation' => 'password123', 'is_active' => 1];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->gala = Event::factory()->create(['name' => 'Gala']);
        $this->forum = Event::factory()->create(['name' => 'Forum']);
        $this->actingAs($this->admin);
    }

    public function test_agent_is_assigned_to_events_when_created(): void
    {
        $this->post(route('users.store'), [...$this->account, 'phone' => '0700000001', 'assignments' => [
            $this->gala->id => 'agent',
            $this->forum->id => 'chief',
        ]])->assertSessionHasNoErrors();

        $awa = User::firstWhere('name', 'Awa');
        $this->assertSame(StaffRole::Agent, $awa->staffRoleFor($this->gala));
        $this->assertSame(StaffRole::Chief, $awa->staffRoleFor($this->forum));
        $this->assertSame(2, AuditLog::where('action', 'staff.added')->count());
    }

    public function test_update_changes_role_removes_and_replaces_the_chief(): void
    {
        $awa = User::factory()->create(['name' => 'Awa']);
        $koffi = User::factory()->create(['name' => 'Koffi']);
        $this->gala->staff()->attach([$awa->id => ['role' => 'agent'], $koffi->id => ['role' => 'chief']]);
        $this->forum->staff()->attach($awa->id, ['role' => 'agent']);

        $this->get(route('users.edit', $awa))->assertOk()->assertSee('Chef : Koffi')->assertSee('Koffi redeviendra agent parking.');

        $this->put(route('users.update', $awa), [
            'name' => 'Awa', 'phone' => $awa->phone, 'role' => 'agent', 'is_active' => 1,
            'assignments' => [$this->gala->id => 'chief', $this->forum->id => ''],
        ])->assertSessionHasNoErrors();

        $this->assertSame(StaffRole::Chief, $awa->staffRoleFor($this->gala));
        $this->assertSame(StaffRole::Agent, $koffi->staffRoleFor($this->gala));
        $this->assertNull($awa->staffRoleFor($this->forum));
        $this->assertTrue(AuditLog::where('action', 'staff.removed')->exists());
    }

    public function test_events_left_out_of_the_form_are_not_touched(): void
    {
        $awa = User::factory()->create();
        $closed = Event::factory()->closed()->create();
        $closed->staff()->attach($awa->id, ['role' => 'agent']);

        // Un événement clôturé auquel le compte est affecté reste listé.
        $this->get(route('users.edit', $awa))->assertSee($closed->name);

        $this->put(route('users.update', $awa), [
            'name' => $awa->name, 'phone' => $awa->phone, 'role' => 'agent', 'is_active' => 1,
            'assignments' => [$this->gala->id => 'agent'],
        ]);

        $this->assertSame(StaffRole::Agent, $awa->staffRoleFor($closed));
        $this->assertSame(StaffRole::Agent, $awa->staffRoleFor($this->gala));
    }

    public function test_invalid_role_is_rejected_before_the_account_is_created(): void
    {
        $this->post(route('users.store'), [...$this->account, 'phone' => '0700000002', 'assignments' => [$this->gala->id => 'boss']])
            ->assertSessionHasErrors("assignments.{$this->gala->id}");

        $this->assertDatabaseMissing('users', ['phone' => '0700000002']);
    }

    public function test_administrators_are_not_assigned(): void
    {
        $this->post(route('users.store'), [
            ...$this->account, 'name' => 'Admin 2', 'email' => 'admin2@example.com', 'role' => 'admin',
            'assignments' => [$this->gala->id => 'agent'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->gala->staff()->count());
    }
}
