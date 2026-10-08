<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class OwnerProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create(['name' => 'Joël']);
        $this->admin = User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => $this->owner->name,
            'email' => $this->owner->email,
            'phone' => $this->owner->phone,
            'role' => 'admin',
            'is_active' => 1,
            ...$overrides,
        ];
    }

    public function test_another_admin_cannot_edit_disable_or_demote_the_owner(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('users.edit', $this->owner))->assertForbidden();
        $this->put(route('users.update', $this->owner), $this->payload(['is_active' => 0]))->assertForbidden();
        $this->put(route('users.update', $this->owner), $this->payload(['role' => 'agent']))->assertForbidden();
        $this->put(route('users.update', $this->owner), $this->payload([
            'password' => 'piratage123', 'password_confirmation' => 'piratage123',
        ]))->assertForbidden();

        $owner = $this->owner->fresh();
        $this->assertTrue($owner->is_active);
        $this->assertTrue($owner->isAdmin());
        $this->assertTrue(password_verify('password', $owner->password));
    }

    public function test_owner_status_cannot_be_given_or_removed_through_the_form(): void
    {
        $this->actingAs($this->owner)
            ->put(route('users.update', $this->admin), [
                'name' => $this->admin->name, 'email' => $this->admin->email, 'role' => 'admin', 'is_active' => 1, 'is_owner' => 1,
            ])
            ->assertRedirect(route('users.index'));

        $this->assertFalse($this->admin->fresh()->isOwner());

        $this->put(route('users.update', $this->owner), $this->payload(['is_owner' => 0]));
        $this->assertTrue($this->owner->fresh()->isOwner());
    }

    public function test_owner_can_edit_own_account_but_stays_active_admin(): void
    {
        $this->actingAs($this->owner);

        $this->put(route('users.update', $this->owner), $this->payload(['name' => 'Joël D.']))
            ->assertRedirect(route('users.index'));
        $this->assertSame('Joël D.', $this->owner->fresh()->name);

        $this->put(route('users.update', $this->owner), $this->payload(['role' => 'agent', 'phone' => '+2250700000099']))
            ->assertSessionHas('error');
        $this->assertTrue($this->owner->fresh()->isAdmin());
    }

    public function test_owner_can_never_be_deleted(): void
    {
        $this->expectException(LogicException::class);

        $this->owner->delete();
    }

    public function test_accounts_list_marks_the_owner_as_protected(): void
    {
        $this->actingAs($this->admin)->get(route('users.index'))
            ->assertSee('Propriétaire')
            ->assertSee('Protégé');

        $this->actingAs($this->owner)->get(route('users.index'))
            ->assertDontSee('Protégé');
    }
}
