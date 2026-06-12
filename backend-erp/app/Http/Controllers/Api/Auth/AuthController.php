<?php
// app/Http/Controllers/Api/Auth/AuthController.php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Resources\UtilisateurResource;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseApiController
{
    // ── Login ──────────────────────────────────────────────
    public function login(LoginRequest $request): JsonResponse
    {
        $utilisateur = Utilisateur::with('role', 'location')
            ->where('email', $request->email)
            ->first();

        if (
            !$utilisateur
            || !Hash::check($request->password, $utilisateur->password)
        ) {
            return $this->error('Email ou mot de passe incorrect.', 401);
        }

        if (!$utilisateur->actif) {
            return $this->error('Compte désactivé. Contactez l\'administrateur.', 403);
        }

        $token = $utilisateur->createToken(
            'cmp-erp-token',
            ['*'],
            now()->addHours(8)
        )->plainTextToken;

        return $this->success([
            'token'        => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => 8 * 3600,
            'utilisateur'  => new UtilisateurResource($utilisateur),
        ], 'Connexion réussie.');
    }

    // ── Logout ─────────────────────────────────────────────
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Déconnexion réussie.');
    }

    // ── Utilisateur courant ────────────────────────────────
    public function me(Request $request): JsonResponse
    {
        return $this->success(
            new UtilisateurResource(
                $request->user()->load('role', 'location')
            )
        );
    }

    // ── Rafraîchir token ───────────────────────────────────
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $token = $user->createToken(
            'cmp-erp-token',
            ['*'],
            now()->addHours(8)
        )->plainTextToken;

        return $this->success([
            'token'      => $token,
            'token_type' => 'Bearer',
            'expires_in' => 8 * 3600,
        ], 'Token rafraîchi.');
    }

    // ── Changer mot de passe ───────────────────────────────
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error('Mot de passe actuel incorrect.', 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);
        $user->tokens()->delete();

        return $this->success(null, 'Mot de passe modifié. Reconnectez-vous.');
    }
}