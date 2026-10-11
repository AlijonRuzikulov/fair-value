<?php

namespace App\Providers;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\CarModel;
use App\Models\Client;
use App\Models\Lead;
use App\Models\RepairNorm;
use App\Models\SmallPart;
use App\Models\SparePart;
use App\Models\User;
use App\Services\CurrencyService;
use App\Support\Registry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            ...Registry::VALUATION_MODELS,
            'client' => Client::class,
            'lead' => Lead::class,
            'user' => User::class,
            'car_model' => CarModel::class,
            'spare_part' => SparePart::class,
            'small_part' => SmallPart::class,
            'repair_norm' => RepairNorm::class,
        ]);

        Model::preventLazyLoading(! $this->app->isProduction());

        Paginator::defaultView('components.pagination');

        // Administrators manage users, settings, directories and the activity log.
        Gate::define('admin', fn (User $user) => $user->role === UserRole::Admin);

        // Operators register clients and objects but do not delete them
        // and do not edit the price catalogues.
        Gate::define('delete-records', fn (User $user) => $user->role !== UserRole::Operator);
        Gate::define('manage-catalogs', fn (User $user) => $user->role !== UserRole::Operator);

        View::composer('components.layouts.admin', function ($view) {
            $view->with('currencyRates', app(CurrencyService::class)->latest());
        });
        View::composer('components.layouts.admin', function ($view) {
            $view->with('newLeadsCount', Lead::where('status', LeadStatus::New)->count());
        });
    }
}
