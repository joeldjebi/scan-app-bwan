<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Closure;
use App\Models\Brand;
use App\Models\Vehicle;
use App\Services\VehicleRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Marques de véhicules proposées dans le formulaire d'enregistrement.
 */
class BrandController extends Controller
{
    public function index(Request $request): View
    {
        $brands = Brand::query()
            ->when($request->q, fn ($query, $q) => $query->where('name', 'like', "%{$q}%"))
            ->when($request->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $usage = Vehicle::whereIn('brand', $brands->pluck('name'))
            ->selectRaw('brand, count(*) as total')
            ->groupBy('brand')
            ->pluck('total', 'brand');

        return view('brands.index', [
            'brands' => $brands,
            'usage' => $usage,
            'activeCount' => Brand::active()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $brand = Brand::create($this->validated($request));

        return back()->with('success', "Marque « {$brand->name} » ajoutée.");
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $brand->update($this->validated($request, $brand));

        return back()->with('success', "Marque « {$brand->name} » mise à jour.");
    }

    public function toggle(Brand $brand): RedirectResponse
    {
        $brand->update(['is_active' => ! $brand->is_active]);

        return back()->with('success', $brand->is_active
            ? "« {$brand->name} » est de nouveau proposée."
            : "« {$brand->name} » n'est plus proposée aux usagers.");
    }

    /**
     * Les véhicules déjà enregistrés gardent leur marque (texte) : la suppression ne les modifie pas.
     */
    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();

        return back()->with('success', "Marque « {$brand->name} » supprimée.");
    }

    /**
     * @return array{name: string, is_active?: bool}
     */
    private function validated(Request $request, ?Brand $brand = null): array
    {
        $request->merge(['name' => trim((string) $request->name)]);

        return $request->validate([
            'name' => [
                'required', 'string', 'max:50',
                Rule::notIn([VehicleRegistration::OTHER_BRAND]),
                function (string $attribute, string $value, Closure $fail) use ($brand) {
                    $exists = Brand::whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->when($brand, fn ($query) => $query->whereKeyNot($brand->id))
                        ->exists();

                    if ($exists) {
                        $fail('Cette marque existe déjà.');
                    }
                },
            ],
        ], [
            'name.not_in' => '« Autre » est réservé : il permet à l\'usager de saisir une marque absente de la liste.',
        ], ['name' => 'nom de la marque']);
    }
}
