<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::findByPhone($data['phone']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            app(AuditLogger::class)->record('auth.login_failed', "Échec de connexion à l'application avec le numéro « {$data['phone']} »", properties: ['telephone' => $data['phone'], 'appareil' => $data['device_name']]);

            throw ValidationException::withMessages(['phone' => 'Numéro de téléphone ou mot de passe incorrect.']);
        }

        if (! $user->is_active) {
            app(AuditLogger::class)->record('auth.blocked', "Connexion refusée à l'application : compte désactivé", $user, ['appareil' => $data['device_name']], actor: $user);

            throw ValidationException::withMessages(['phone' => 'Votre compte est désactivé.']);
        }

        $token = $user->createToken($data['device_name']);
        app(AuditLogger::class)->record('auth.login', "Connexion à l'application sur « {$data['device_name']} »", $user, ['appareil' => $data['device_name']], actor: $user);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        app(AuditLogger::class)->record('auth.logout', "Déconnexion de l'application", $request->user());
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
        ];
    }
}
