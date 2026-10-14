<?php

namespace App\Providers;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Enums\ValuationStatus;
use App\Models\CarModel;
use App\Models\Client;
use App\Models\Lead;
use App\Models\RepairNorm;
use App\Models\SmallPart;
use App\Models\SparePart;
use App\Models\User;
use App\Services\Analogs\ClaudeListingAi;
use App\Services\Analogs\ListingAi;
use App\Services\CurrencyService;
use App\Support\Registry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ListingAi::class, ClaudeListingAi::class);
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

        // Behind an https proxy (Cloudflare, a hosting panel, Codespaces) links stay https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Paginator::defaultView('components.pagination');

        // Administrators manage users, settings, directories and the activity log.
        Gate::define('admin', fn (User $user) => $user->role === UserRole::Admin);

        // Operators register clients and objects but do not delete them
        // and do not edit the price catalogues.
        Gate::define('delete-records', fn (User $user) => $user->role !== UserRole::Operator);
        Gate::define('manage-catalogs', fn (User $user) => $user->role !== UserRole::Operator);

        // Completed reports can be checked on the website, so only appraisers
        // and administrators complete them or change them afterwards.
        Gate::define('finalize-valuations', fn (User $user) => $user->role !== UserRole::Operator);
        Gate::define('edit-valuation', fn (User $user, Model $item) => $item->status !== ValuationStatus::Completed || $user->can('finalize-valuations'));

        // Password guessing: per account and address, per account, and per address.
        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by('login:'.$email.'|'.$request->ip()),
                Limit::perHour(50)->by('login:'.$email),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        View::composer('components.layouts.admin', function ($view) {
            $view->with('currencyRates', app(CurrencyService::class)->latest());
        });
        View::composer('components.layouts.admin', function ($view) {
            $view->with('newLeadsCount', Lead::where('status', LeadStatus::New)->count());
        });
    }
}
