<?php

namespace App\Http\Controllers\Api\Stock;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\MouvementStockResource;
use App\Models\ClassementProduit;
use App\Models\MatierePremiere;
use App\Models\MouvementStock;
use App\Models\Produit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MouvementStockController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = MouvementStock::with('location', 'utilisateur', 'classement', 'entite');

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('entite_type')) {
            $query->where('entite_type', $request->entite_type);
        }

        if ($request->filled('entite_id')) {
            $query->where('entite_id', $request->entite_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        if ($request->filled('date_debut')) {
            $query->where('date_mouvement', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->where('date_mouvement', '<=', $request->date_fin . ' 23:59:59');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $like = '%' . $search . '%';

            $produitIds = Produit::query()
                ->where(function ($q) use ($like) {
                    $q->where('designation', 'like', $like)
                        ->orWhere('nomencla', 'like', $like);
                })
                ->pluck('id')
                ->all();

            $matiereIds = MatierePremiere::query()
                ->where(function ($q) use ($like) {
                    $q->where('nom', 'like', $like)
                        ->orWhere('reference', 'like', $like);
                })
                ->pluck('id')
                ->all();

            $classementIds = ClassementProduit::query()
                ->where(function ($q) use ($like) {
                    $q->where('libelle', 'like', $like)
                        ->orWhere('qualite', 'like', $like);
                })
                ->pluck('id')
                ->all();

            $query->where(function ($searchQuery) use ($like, $produitIds, $matiereIds, $classementIds) {
                $searchQuery->where('reference_type', 'like', $like)
                    ->orWhere('motif', 'like', $like)
                    ->orWhereRaw('CAST(reference_id AS CHAR) LIKE ?', [$like])
                    ->orWhereHas('location', function ($locationQuery) use ($like) {
                        $locationQuery->where('nom', 'like', $like);
                    })
                    ->orWhereHas('utilisateur', function ($userQuery) use ($like) {
                        $userQuery->where('nom', 'like', $like);
                    });

                if (!empty($classementIds)) {
                    $searchQuery->orWhereIn('classement_id', $classementIds);
                }

                if (!empty($produitIds)) {
                    $searchQuery->orWhere(function ($produitQuery) use ($produitIds) {
                        $produitQuery->where('entite_type', 'produit')
                            ->whereIn('entite_id', $produitIds);
                    });
                }

                if (!empty($matiereIds)) {
                    $searchQuery->orWhere(function ($matiereQuery) use ($matiereIds) {
                        $matiereQuery->where('entite_type', 'matiere')
                            ->whereIn('entite_id', $matiereIds);
                    });
                }
            });
        }

        $mouvements = $query
            ->orderByDesc('date_mouvement')
            ->paginate((int) $request->get('per_page', 10))
            ->appends($request->query());

        return $this->success(
            MouvementStockResource::collection($mouvements)->response()->getData(true)
        );
    }

    public function show(int $id): JsonResponse
    {
        $mouvement = MouvementStock::with(
            'location', 'utilisateur', 'classement', 'entite'
        )->findOrFail($id);

        return $this->success(new MouvementStockResource($mouvement));
    }
}