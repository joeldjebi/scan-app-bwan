<?php

namespace App\Http\Controllers\Web;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->q, fn ($query, $q) => $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->when(User::normalizePhone($q), fn ($sub, $phone) => $sub->orWhere('phone', 'like', "%{$phone}%"))))
            ->withCount('events')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User(['role' => UserRole::Agent, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        User::create($this->validated($request));

        return redirect()->route('users.index')->with('success', 'Compte créé.');
    }

    public function edit(User $user): View
    {
        return view('users.form', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && (! $data['is_active'] || $data['role'] !== UserRole::Admin->value)) {
            return back()->with('error', 'Vous ne pouvez pas retirer vos propres droits administrateur.');
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return redirect()->route('users.index')->with('success', 'Compte mis à jour.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge([
            'phone' => User::normalizePhone($request->phone),
            'email' => $request->filled('email') ? trim($request->email) : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Identifiant de connexion des agents et chefs (application mobile et back-office).
            'phone' => ['required_if:role,'.UserRole::Agent->value, 'nullable', 'regex:/^\+?\d{8,15}$/', Rule::unique('users')->ignore($user)],
            // Identifiant de connexion des administrateurs.
            'email' => ['required_if:role,'.UserRole::Admin->value, 'nullable', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ], [
            'phone.required_if' => 'Le numéro de téléphone est obligatoire pour un agent : il lui sert d\'identifiant.',
            'phone.regex' => 'Le numéro de téléphone n\'est pas valide (8 à 15 chiffres, indicatif + facultatif).',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé par un autre compte.',
            'email.required_if' => 'L\'email est obligatoire pour un administrateur : il lui sert d\'identifiant.',
        ]);

        return [...$data, 'is_active' => $request->boolean('is_active')];
    }
}
