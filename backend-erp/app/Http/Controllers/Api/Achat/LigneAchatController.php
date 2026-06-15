<?php
// app/Http/Controllers/Api/Achat/LigneAchatController.php

namespace App\Http\Controllers\Api\Achat;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\JournalAchat;
use App\Models\LigneAchat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LigneAchatController extends BaseApiController
{
    public function index(JournalAchat $bonsReception): JsonResponse
    {
        $lignes = $bonsReception->lignes()->with('matiere')->get();

        return $this->success($lignes);
    }

    public function store(Request $request, JournalAchat $bonsReception): JsonResponse
    {
        if ($bonsReception->statut === 'valide') {
            return $this->error('Un BR validé ne peut pas être modifié.', 422);
        }

        $validated = $request->validate([
            'matiere_id'       => ['required', 'exists:matieres_premieres,id'],
            'quantite'         => ['required', 'numeric', 'min:0.001'],
            'prix_unitaire'    => ['required', 'numeric', 'min:0'],
            'observations_ligne' => ['nullable', 'string'],
        ]);

        $totalLigne = round($validated['quantite'] * $validated['prix_unitaire'], 2);

        $ligne = LigneAchat::create([
            'journal_achat_id' => $bonsReception->id,
            ...$validated,
            'total_ligne' => $totalLigne,
        ]);

        $bonsReception->update(['total' => $bonsReception->calculerTotal()]);

        return $this->created($ligne->load('matiere'));
    }

    public function show(LigneAchat $ligneAchat): JsonResponse
    {
        return $this->success($ligneAchat->load('matiere'));
    }

    public function update(Request $request, LigneAchat $ligneAchat): JsonResponse
    {
        if ($ligneAchat->journalAchat->statut === 'valide') {
            return $this->error('Un BR validé ne peut pas être modifié.', 422);
        }

        $validated = $request->validate([
            'quantite'      => ['sometimes', 'numeric', 'min:0.001'],
            'prix_unitaire' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $ligneAchat->update($validated);
        $ligneAchat->update(['total_ligne' => $ligneAchat->calculerTotalLigne()]);

        $ligneAchat->journalAchat->update([
            'total' => $ligneAchat->journalAchat->calculerTotal(),
        ]);

        return $this->success($ligneAchat->fresh('matiere'), 'Ligne mise à jour.');
    }

    public function destroy(LigneAchat $ligneAchat): JsonResponse
    {
        if ($ligneAchat->journalAchat->statut === 'valide') {
            return $this->error('Un BR validé ne peut pas être modifié.', 422);
        }

        $br = $ligneAchat->journalAchat;
        $ligneAchat->delete();
        $br->update(['total' => $br->calculerTotal()]);

        return $this->success(null, 'Ligne supprimée.');
    }
}