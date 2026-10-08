@php
    $vehicle ??= null;
    $currentBrand = old('brand', $vehicle ? (in_array($vehicle->brand, $brands) ? $vehicle->brand : \App\Services\VehicleRegistration::OTHER_BRAND) : '');
    $otherBrand = old('brand_other', $vehicle && ! in_array($vehicle->brand, $brands) ? $vehicle->brand : '');
    $input = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none';
@endphp
<div class="space-y-4">
    <div>
        <label for="plate" class="block text-sm font-medium text-slate-700">Immatriculation <span class="text-red-600" aria-hidden="true">*</span></label>
        <input id="plate" name="plate" type="text" required maxlength="20" autocomplete="off" autocapitalize="characters"
               value="{{ old('plate', $vehicle?->plate) }}" placeholder="Ex. 1234 AB 01"
               class="{{ $input }} font-mono uppercase tracking-wider">
    </div>

    <div>
        <label for="brand" class="block text-sm font-medium text-slate-700">Marque du véhicule <span class="text-red-600" aria-hidden="true">*</span></label>
        @include('partials.brand-picker', [
            'current' => $currentBrand,
            'other' => $otherBrand,
            'inputClass' => $input,
            'otherInputClass' => $input,
            'optionClass' => 'flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-sm',
            'activeClass' => 'bg-indigo-50 text-indigo-700',
        ])
    </div>

    <div>
        <label for="color" class="block text-sm font-medium text-slate-700">Couleur du véhicule <span class="text-red-600" aria-hidden="true">*</span></label>
        <input id="color" name="color" type="text" required maxlength="30" list="vehicle-colors"
               value="{{ old('color', $vehicle?->color) }}" placeholder="Ex. Blanc" class="{{ $input }}">
        <datalist id="vehicle-colors">
            @foreach (array_keys($colors) as $color)
                <option value="{{ $color }}">
            @endforeach
        </datalist>
    </div>

    <div>
        <label for="phone" class="block text-sm font-medium text-slate-700">Numéro de téléphone <span class="text-red-600" aria-hidden="true">*</span></label>
        <input id="phone" name="phone" type="tel" required inputmode="tel" autocomplete="tel"
               value="{{ old('phone', $vehicle?->phone) }}" placeholder="Ex. +225 07 00 00 00 00" class="{{ $input }}">
    </div>
</div>
