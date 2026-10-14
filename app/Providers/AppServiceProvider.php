<?php

namespace App\Providers;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Enums\ValuationStatus;
use App\Models\Activity;
use App\Models\CarModel;
use App\Models\Client;
use App\Models\Lead;
use App\Models\RepairNorm;
use App\Models\SmallPart;
use App\Models\SparePart;
use App\Models\User;
use App\Services\Analogs\ClaudeListingAi;
use App\Services\Analogs\ListingAi;
use App\Services\Audit\SecurityAlert;
use App\Services\CurrencyService;
use App\Services\Documents\ClaudeDocumentAi;
use App\Services\Documents\DocumentAi;
use App\Support\Registry;
use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
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
    /** Signing in and out goes to the audit log, whichever way it happens. */
    private function auditSignIns(): void
    {
        Event::listen(Login::class, fn (Login $e) => Activity::record('login', $e->user, $e->remember ? ['remember' => true] : null, $e->user->name, $e->user));

        Event::listen(Logout::class, function (Logout $e) {
            if (! $e->user) {
                return;
            }
            $reason = request()->attributes->get('audit.logout_reason');
            Activity::record($reason ? 'session_ended' : 'logout', $e->user, $reason ? ['reason' => $reason] : null, $e->user->name, $e->user);
        });

        // The password was changed elsewhere: this device is signed out.
        Event::listen(CurrentDeviceLogout::class, fn (CurrentDeviceLogout $e) => $e->user
            && Activity::record('session_ended', $e->user, ['reason' => 'password_changed'], $e->user->name, $e->user));

        Event::listen(Failed::class, function (Failed $e) {
            $email = mb_substr(mb_strtolower((string) ($e->credentials['email'] ?? '')), 0, 255);
            Activity::record('login_failed', $e->user, ['reason' => $e->user ? 'wrong_password' : 'unknown_email'], $email, email: $email);

            $window = 'audit:failed:'.intdiv(time(), 900);
            Cache::add($window, 0, now()->addMinutes(20));
            if (Cache::increment($window) >= 10) {
                SecurityAlert::send('failed-burst', __('Many failed sign-in attempts in the last 15 minutes. Check the audit log.'));
            }
        });
    }

    public function register(): void
    {
        $this->app->bind(ListingAi::class, ClaudeListingAi::class);
        $this->app->bind(DocumentAi::class, ClaudeDocumentAi::class);
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

            $blocked = function (Request $request, array $headers) use ($email) {
                if (Cache::add('audit:locked:'.$email.'|'.$request->ip(), true, now()->addMinute())) {
                    Activity::record('login_locked', User::where('email', $email)->first(), ['ip' => $request->ip()], $email, email: $email);
                    SecurityAlert::send('locked:'.$email, __('Sign-in to :email was blocked after repeated wrong passwords (IP :ip).', ['email' => $email, 'ip' => $request->ip()]));
                }

                throw new ThrottleRequestsException(__('Too many attempts. Try again in a minute.'), null, $headers);
            };

            return [
                Limit::perMinute(5)->by('login:'.$email.'|'.$request->ip())->response($blocked),
                Limit::perHour(50)->by('login:'.$email)->response($blocked),
                Limit::perMinute(20)->by('login-ip:'.$request->ip())->response($blocked),
            ];
        });

        $this->auditSignIns();

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        View::composer('components.layouts.admin', function ($view) {
            $view->with('currencyRates', app(CurrencyService::class)->latest());
        });
        View::composer('components.layouts.admin', function ($view) {
            $view->with('newLeadsCount', Lead::where('status', LeadStatus::New)->count());
        });
    }
}
