<?php
// app/Http/Controllers/Api/Rh/EmployeController.php

namespace App\Http\Controllers\Api\Rh;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\EmployeResource;
use App\Models\Employe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Employe::with('poste');

        if ($request->filled('poste_id')) {
            $query->where('poste_id', $request->poste_id);
        }

        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nom', 'like', "%{$request->search}%")
                  ->orWhere('prenom', 'like', "%{$request->search}%")
                  ->orWhere('matricule', 'like', "%{$request->search}%");
            });
        }

        $employes = $query
            ->orderBy('nom')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            EmployeResource::collection($employes)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'matricule'     => ['required', 'string', 'max:20', 'unique:employes,matricule'],
            'nom'           => ['required', 'string', 'max:100'],
            'prenom'        => ['required', 'string', 'max:100'],
            'poste_id'      => ['required', 'exists:postes,id'],
            'date_embauche' => ['required', 'date'],
            'date_depart'   => ['nullable', 'date', 'after:date_embauche'],
            'actif'         => ['boolean'],
        ]);

        $employe = Employe::create($validated);

        return $this->created(new EmployeResource($employe->load('poste')));
    }

    public function show(Employe $employe): JsonResponse
    {
        $employe->load('poste');

        return $this->success(new EmployeResource($employe));
    }

    public function update(Request $request, Employe $employe): JsonResponse
    {
        $validated = $request->validate([
            'nom'           => ['sometimes', 'string', 'max:100'],
            'prenom'        => ['sometimes', 'string', 'max:100'],
            'poste_id'      => ['sometimes', 'exists:postes,id'],
            'date_embauche' => ['sometimes', 'date'],
            'date_depart'   => ['nullable', 'date'],
            'actif'         => ['sometimes', 'boolean'],
        ]);

        $employe->update($validated);

        return $this->success(
            new EmployeResource($employe->fresh('poste')),
            'Employé mis à jour.'
        );
    }

    public function destroy(Employe $employe): JsonResponse
    {
        $employe->delete();

        return $this->success(null, 'Employé archivé.');
    }
}