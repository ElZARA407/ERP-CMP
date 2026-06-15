<?php

namespace App\Providers;

use App\Models\Commande;
use App\Models\Facture;
use App\Models\BonProduction;
use App\Models\Stock;
use App\Policies\CommandePolicy;
use App\Policies\FacturePolicy;
use App\Policies\ProductionPolicy;
use App\Policies\StockPolicy;
use App\Repositories\Contracts\StockRepositoryInterface;
use App\Repositories\StockRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;


/**
 * LARAVEL 13 :
 * - Kernel.php supprimé → tout est dans AppServiceProvider
 * - Queue::route() pour centraliser la configuration des queues
 * - Gate::policy() pour enregistrer les Policies
 * - Binding Repository → Interface dans le container IoC
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── Binding Repository ──────────────────────────────
        $this->app->bind(
            StockRepositoryInterface::class,
            StockRepository::class
        );
    }

    public function boot(): void
    {
        // ── Policies ────────────────────────────────────────
        Gate::policy(Commande::class,      CommandePolicy::class);
        Gate::policy(Facture::class,       FacturePolicy::class);
        Gate::policy(BonProduction::class, ProductionPolicy::class);
        Gate::policy(Stock::class,         StockPolicy::class);

        Schema::defaultStringLength(191);

        // 👇 Ajouter ceci
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
        // ── Queue routing Laravel 13 ─────────────────────────
        // Centralise la configuration des queues en un seul endroit
        // Plus besoin de définir $connection et $queue sur chaque Job
        Queue::route([
            // Les Jobs seront créés dans une Phase 2
            // Exemple :
            // ValiderSessionProductionJob::class => 'production@database',
            // GenererFactureJob::class           => 'finance@database',
            // RecalculerStockJob::class          => 'stock@database',
        ]);
    }
}
