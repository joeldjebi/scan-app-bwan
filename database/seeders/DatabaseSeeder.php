<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;
use App\Services\PassGenerator;
use App\Services\VehicleRegistration;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BrandSeeder::class);

        $admin = User::updateOrCreate(['email' => 'admin@passparking.test'], [
            'name' => 'Administrateur',
            'password' => 'password',
            'role' => UserRole::Admin,
        ]);

        if (! User::where('is_owner', true)->exists()) {
            $admin->forceFill(['is_owner' => true])->save();
        }

        if (! app()->isLocal()) {
            return;
        }

        // Données de démonstration (environnement local uniquement).
        $chief = User::updateOrCreate(['email' => 'chef@passparking.test'], [
            'name' => 'Koffi Chef', 'phone' => '+2250701010101', 'password' => 'password', 'role' => UserRole::Agent,
        ]);
        $agents = collect(['Awa', 'Moussa', 'Fatou'])->map(fn ($name, $i) => User::updateOrCreate(
            ['email' => strtolower($name).'@passparking.test'],
            ['name' => "{$name} Agent", 'phone' => sprintf('+22507020202%02d', $i), 'password' => 'password', 'role' => UserRole::Agent],
        ));

        $event = Event::firstOrCreate(['code' => 'DEMO26'], [
            'name' => 'Festival de démonstration',
            'location' => 'Palais de la Culture',
            'starts_at' => now()->addWeek()->setTime(18, 0),
            'ends_at' => now()->addWeek()->setTime(23, 59),
            'status' => EventStatus::Active,
        ]);

        $event->staff()->syncWithoutDetaching([
            $chief->id => ['role' => StaffRole::Chief->value],
            ...$agents->mapWithKeys(fn ($agent) => [$agent->id => ['role' => StaffRole::Agent->value]]),
        ]);

        if ($event->passes()->exists()) {
            return;
        }

        $generator = app(PassGenerator::class);
        foreach ([['VIP', 'VIP', '#b45309', 20], ['Staff', 'STF', '#059669', 30], ['Presse', 'PRS', '#7c3aed', 10]] as [$name, $code, $color, $count]) {
            $generator->generate($event->passTypes()->create(compact('name', 'code', 'color')), $count);
        }

        $registration = app(VehicleRegistration::class);
        $event->passes()->limit(8)->get()->each(fn ($pass, $i) => $registration->save($pass, [
            'plate' => sprintf('%04d AB 01', 1000 + $i),
            'brand' => fake()->randomElement(['Toyota', 'Hyundai', 'Mercedes-Benz', 'Kia']),
            'color' => fake()->randomElement(array_keys(config('parking.colors'))),
            'phone' => '+22507'.fake()->numerify('########'),
        ]));
    }
}
