<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportsOverviewExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $payload,
        private readonly string $section
    ) {}

    public function sheets(): array
    {
        return match ($this->section) {
            'commercial' => [
                new SimpleArraySheet('Ventes produits', $this->payload['commercial']['ventes_par_produit'] ?? []),
                new SimpleArraySheet('Ventes clients', $this->payload['commercial']['ventes_par_client'] ?? []),
                new SimpleArraySheet('Commandes ouvertes', $this->payload['commercial']['commandes_non_livrees'] ?? []),
            ],
            'stock' => [
                new SimpleArraySheet('Produits sous minimum', $this->payload['stock']['produits_sous_minimum'] ?? []),
                new SimpleArraySheet('Matières sous minimum', $this->payload['stock']['matieres_sous_minimum'] ?? []),
                new SimpleArraySheet('Mouvements stock', $this->payload['stock']['mouvements'] ?? []),
            ],
            'production' => [
                new SimpleArraySheet('Objectif réalisé', $this->payload['production']['objectif_vs_realise'] ?? []),
                new SimpleArraySheet('Par machine', $this->payload['production']['production_par_machine'] ?? []),
                new SimpleArraySheet('Consommation MP', $this->payload['production']['consommation_matiere'] ?? []),
            ],
            'recyclage' => [
                new SimpleArraySheet('Quantité transformée', $this->payload['recyclage']['quantite_transformee'] ?? []),
                new SimpleArraySheet('Evolution mensuelle', $this->payload['recyclage']['evolution_mensuelle'] ?? []),
            ],
            'finance' => [
                new SimpleArraySheet('Clients débiteurs', $this->payload['finance']['clients_debiteurs'] ?? []),
                new SimpleArraySheet('Résumé finance', [$this->payload['finance'] ?? []]),
            ],
            'mouvements' => [
                new SimpleArraySheet('Mouvements', $this->payload['mouvements']['lignes'] ?? []),
            ],
            default => [
                new SimpleArraySheet('Rapport', []),
            ],
        };
    }
}