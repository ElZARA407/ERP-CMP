<?php

namespace App\Http\Controllers\Api\Production;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\MachineResource;
use App\Models\Machine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MachineController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Machine::query();

        if (!$request->has('actif')) {
            $query->where('actif', true);
        } elseif ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('nom', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $machines = $query->orderBy('nom')->get();

        return $this->success(MachineResource::collection($machines));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:100', 'unique:machines,nom'],
            'description' => ['nullable', 'string', 'max:500'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $machine = Machine::create([
            'nom' => $validated['nom'],
            'description' => $validated['description'] ?? null,
            'actif' => $validated['actif'] ?? true,
        ]);

        return $this->created(new MachineResource($machine));
    }

    public function show(Machine $machine): JsonResponse
    {
        return $this->success(new MachineResource($machine));
    }

    public function update(Request $request, Machine $machine): JsonResponse
    {
        $validated = $request->validate([
            'nom' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('machines', 'nom')->ignore($machine->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $machine->update($validated);

        return $this->success(new MachineResource($machine->fresh()), 'Machine mise à jour.');
    }

    public function destroy(Machine $machine): JsonResponse
    {
        $hasRelations = $machine->bonsProduction()->exists()
            || $machine->sessionsProduction()->exists();

        if ($hasRelations) {
            return $this->error(
                'Cette machine ne peut pas être désactivée car elle est utilisée.',
                422
            );
        }

        $machine->update(['actif' => false]);

        return $this->success(null, 'Machine désactivée.');
    }
}