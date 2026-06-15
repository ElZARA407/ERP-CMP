<?php
// app/Http/Controllers/Api/Organisation/LocationController.php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Location::query();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $query->where('nom', 'like', "%{$request->search}%");
        }

        $locations = $query->orderBy('nom')->get();

        return $this->success(LocationResource::collection($locations));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'  => ['required', 'string', 'max:100', 'unique:locations,nom'],
            'type' => ['required', 'in:bureau,usine'],
        ]);

        $location = Location::create($validated);

        return $this->created(new LocationResource($location));
    }

    public function show(Location $location): JsonResponse
    {
        return $this->success(new LocationResource($location));
    }

    public function update(Request $request, Location $location): JsonResponse
    {
        $validated = $request->validate([
            'nom'  => ['sometimes', 'string', 'max:100', "unique:locations,nom,{$location->id}"],
            'type' => ['sometimes', 'in:bureau,usine'],
        ]);

        $location->update($validated);

        return $this->success(
            new LocationResource($location->fresh()),
            'Site mis à jour.'
        );
    }

    public function destroy(Location $location): JsonResponse
    {
        $hasRelations = $location->utilisateurs()->exists()
            || $location->stocks()->exists()
            || $location->bonsProduction()->exists();

        if ($hasRelations) {
            return $this->error(
                'Ce site ne peut pas être supprimé car il est utilisé.',
                422
            );
        }

        $location->delete();

        return $this->success(null, 'Site supprimé.');
    }
}