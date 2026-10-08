<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Pass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandManagementTest extends TestCase
{
    use RefreshDatabase;

    private array $vehicle = ['plate' => '1234 AB 01', 'color' => 'Blanc', 'phone' => '0700000000'];

    public function test_seeder_provides_common_brands(): void
    {
        $this->assertGreaterThanOrEqual(50, Brand::count());
        $this->assertDatabaseHas('brands', ['name' => 'Toyota', 'is_active' => true]);
    }

    public function test_admin_manages_brands(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->post(route('brands.store'), ['name' => '  Lada  '])->assertSessionHasNoErrors();
        $brand = Brand::firstWhere('name', 'Lada');
        $this->assertNotNull($brand);

        $this->post(route('brands.store'), ['name' => 'lada'])->assertSessionHasErrors('name');
        $this->post(route('brands.store'), ['name' => 'Autre'])->assertSessionHasErrors('name');

        $this->put(route('brands.update', $brand), ['name' => 'Lada Niva'])->assertSessionHasNoErrors();
        $this->patch(route('brands.toggle', $brand));
        $this->assertFalse($brand->fresh()->is_active);

        $this->get(route('brands.index', ['q' => 'Niva']))->assertOk()->assertSee('Lada Niva')->assertSee('Masquée');

        $this->delete(route('brands.destroy', $brand));
        $this->assertModelMissing($brand);
    }

    public function test_registration_form_only_accepts_active_brands(): void
    {
        $hidden = Brand::factory()->inactive()->create(['name' => 'Trabant']);
        $pass = Pass::factory()->create();

        $this->get(route('public.pass', $pass->token))->assertSee('Toyota')->assertDontSee('Trabant');

        $this->post(route('public.pass.register', $pass->token), [...$this->vehicle, 'brand' => $hidden->name])
            ->assertSessionHasErrors('brand');

        $this->post(route('public.pass.register', $pass->token), [...$this->vehicle, 'brand' => 'Toyota'])
            ->assertSessionHasNoErrors();
    }

    public function test_deleting_a_brand_keeps_existing_vehicles(): void
    {
        $admin = User::factory()->admin()->create();
        $pass = Pass::factory()->registered(['brand' => 'Toyota'])->create();

        $this->actingAs($admin)->delete(route('brands.destroy', Brand::firstWhere('name', 'Toyota')));

        $this->assertSame('Toyota', $pass->fresh()->vehicle->brand);
    }

    public function test_only_admin_manages_brands(): void
    {
        $this->actingAs(User::factory()->create())->get(route('brands.index'))->assertForbidden();
        $this->post(route('brands.store'), ['name' => 'Lada'])->assertForbidden();
    }
}
