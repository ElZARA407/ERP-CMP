<?php

namespace App\Providers;

use App\Models\BonProduction;
use App\Models\Commande;
use App\Models\Facture;
use App\Models\Role;
use App\Models\Stock;
use App\Policies\CommandePolicy;
use App\Policies\FacturePolicy;
use App\Policies\ProductionPolicy;
use App\Policies\RolePolicy;
use App\Policies\StockPolicy;
use App\Repositories\Contracts\StockRepositoryInterface;
use App\Repositories\StockRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * LARAVEL 13 :
 * - Kernel.php supprimé -> tout est dans AppServiceProvider
 * - Queue::route() pour centraliser la configuration des queues
 * - Gate::policy() pour enregistrer les Policies
 * - Binding Repository -> Interface dans le container IoC
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
        Gate::policy(Commande::class, CommandePolicy::class);
        Gate::policy(Facture::class, FacturePolicy::class);
        Gate::policy(BonProduction::class, ProductionPolicy::class);
        Gate::policy(Stock::class, StockPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        Schema::defaultStringLength(191);

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
        Relation::morphMap([
            'produit' => \App\Models\Produit::class,
            'matiere' => \App\Models\MatierePremiere::class,
        ]);
        // ── Queue routing Laravel 13 ─────────────────────────
        Queue::route([
            // Les Jobs seront créés dans une Phase 2
            // Exemple :
            // ValiderSessionProductionJob::class => 'production@database',
            // GenererFactureJob::class           => 'finance@database',
            // RecalculerStockJob::class          => 'stock@database',
        ]);
    }
}