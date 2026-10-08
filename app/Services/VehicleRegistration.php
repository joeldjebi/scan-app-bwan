<?php

namespace App\Services;

use App\Enums\PassStatus;
use App\Models\Brand;
use App\Models\Pass;
use App\Models\Vehicle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VehicleRegistration
{
    public const OTHER_BRAND = 'Autre';

    /**
     * Marques actives (gérées par l'administrateur), triées, suivies de « Autre ».
     *
     * @return list<string>
     */
    public static function brands(): array
    {
        return Cache::rememberForever(Brand::CACHE_KEY, function () {
            $brands = Brand::active()->pluck('name')->all();
            sort($brands, SORT_NATURAL | SORT_FLAG_CASE);

            return [...$brands, self::OTHER_BRAND];
        });
    }

    /**
     * Couleurs proposées en pastilles : nom => teinte.
     *
     * @return array<string, string>
     */
    public static function colors(): array
    {
        return config('parking.colors');
    }

    public static function rules(): array
    {
        return [
            'plate' => ['required', 'string', 'min:2', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9 .\-]*$/'],
            'brand' => ['required', 'string', Rule::in(self::brands())],
            'brand_other' => ['nullable', 'required_if:brand,'.self::OTHER_BRAND, 'string', 'max:50'],
            'color' => ['required', 'string', 'max:30'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9][0-9 .\-]{7,19}$/'],
        ];
    }

    public static function messages(): array
    {
        return [
            'plate.regex' => 'L\'immatriculation ne doit contenir que des lettres, chiffres, espaces ou tirets.',
            'phone.regex' => 'Le numéro de téléphone n\'est pas valide.',
            'brand.in' => 'Veuillez choisir une marque dans la liste.',
            'brand_other.required_if' => 'Veuillez préciser la marque.',
        ];
    }

    public static function attributes(): array
    {
        return [
            'plate' => 'immatriculation',
            'brand' => 'marque',
            'brand_other' => 'marque',
            'color' => 'couleur',
            'phone' => 'téléphone',
        ];
    }

    /**
     * Enregistre (ou met à jour) le véhicule d'un pass et active le pass.
     *
     * @param  array{plate: string, brand: string, brand_other?: ?string, color: string, phone: string}  $data
     */
    public function save(Pass $pass, array $data): Vehicle
    {
        $attributes = [
            'event_id' => $pass->event_id,
            'plate' => $data['plate'],
            'brand' => $data['brand'] === self::OTHER_BRAND ? trim($data['brand_other']) : $data['brand'],
            'color' => ucfirst(trim($data['color'])),
            'phone' => preg_replace('/[^\d+]/', '', $data['phone']),
        ];

        $duplicate = Vehicle::where('event_id', $pass->event_id)
            ->where('plate_key', Vehicle::plateKey($data['plate']))
            ->where('pass_id', '!=', $pass->id)
            ->exists();

        if ($duplicate) {
            throw $this->duplicatePlate();
        }

        try {
            return DB::transaction(function () use ($pass, $attributes) {
                $vehicle = $pass->vehicle()->updateOrCreate([], $attributes);

                if ($pass->status === PassStatus::Pending) {
                    $pass->update(['status' => PassStatus::Registered, 'registered_at' => now()]);
                }

                return $vehicle;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicatePlate();
        }
    }

    private function duplicatePlate(): ValidationException
    {
        return ValidationException::withMessages([
            'plate' => 'Ce véhicule est déjà enregistré sur un autre pass de cet événement.',
        ]);
    }
}
