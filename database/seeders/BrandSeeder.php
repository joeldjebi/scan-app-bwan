<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Marques les plus courantes. Idempotent : ne modifie pas les marques existantes.
     */
    public function run(): void
    {
        $brands = [
            'Acura', 'Alfa Romeo', 'Audi', 'Bentley', 'BMW', 'BYD', 'Cadillac', 'Changan', 'Chery',
            'Chevrolet', 'Citroën', 'Dacia', 'Dodge', 'Ferrari', 'Fiat', 'Ford', 'Geely', 'GMC',
            'Great Wall', 'Haval', 'Honda', 'Hyundai', 'Infiniti', 'Isuzu', 'JAC', 'Jaguar', 'Jeep',
            'Kia', 'Lamborghini', 'Land Rover', 'Lexus', 'Maserati', 'Mazda', 'Mercedes-Benz', 'Mini',
            'Mitsubishi', 'Nissan', 'Opel', 'Peugeot', 'Porsche', 'Range Rover', 'Renault',
            'Rolls-Royce', 'Seat', 'Skoda', 'Subaru', 'Suzuki', 'Tesla', 'Toyota', 'Volkswagen', 'Volvo',
        ];

        foreach ($brands as $name) {
            Brand::firstOrCreate(['name' => $name]);
        }
    }
}
