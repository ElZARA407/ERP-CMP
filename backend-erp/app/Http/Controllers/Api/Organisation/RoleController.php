<?php
// app/Http/Controllers/Api/Organisation/RoleController.php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::withCount('utilisateurs')->orderBy('nom')->get();

        return $this->success(RoleResource::collection($roles));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $validated = $request->validate([
            'nom'         => ['required', 'string', 'max:50', 'unique:roles,nom'],
            'description' => ['nullable', 'string'],
        ]);

        $role = Role::create($validated)->loadCount('utilisateurs');

        return $this->created(new RoleResource($role));
    }

    public function show(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        $role->loadCount('utilisateurs');

        return $this->success(new RoleResource($role));
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $validated = $request->validate([
            'nom'         => ['sometimes', 'string', 'max:50', "unique:roles,nom,{$role->id}"],
            'description' => ['nullable', 'string'],
        ]);

        $role->update($validated);

        return $this->success(
            new RoleResource($role->fresh()->loadCount('utilisateurs')),
            'Rôle mis à jour.'
        );
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        if ($role->utilisateurs()->exists()) {
            return $this->error(
                'Ce rôle ne peut pas être supprimé : des utilisateurs y sont rattachés.',
                422
            );
        }

        $role->delete();

        return $this->success(null, 'Rôle supprimé.');
    }
}