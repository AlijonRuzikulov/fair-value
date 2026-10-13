<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Site;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
*/

Route::get('/', [Site\HomeController::class, 'index'])->name('home');
Route::get('/services', [Site\ServiceController::class, 'index'])->name('services');
Route::get('/services/{slug}', [Site\ServiceController::class, 'show'])->name('services.show');
Route::get('/about', [Site\PageController::class, 'about'])->name('about');
Route::get('/contact', [Site\PageController::class, 'contact'])->name('contact');
Route::get('/verify', [Site\VerifyController::class, 'show'])->middleware('throttle:30,1')->name('verify');
Route::get('/v/{code}', fn (string $code) => redirect()->route('verify', ['code' => $code]))->name('verify.short');
Route::post('/request', [Site\LeadController::class, 'store'])->middleware('throttle:6,1')->name('lead.store');
Route::get('/lang/{locale}', LocaleController::class)->name('locale');
Route::get('/sitemap.xml', [Site\PageController::class, 'sitemap'])->name('sitemap');

/*
|--------------------------------------------------------------------------
| Sign in
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
*/

// auth.session signs out other devices when a password is changed.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'auth.session', 'active'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    // Clients
    Route::get('clients/search', [Admin\ClientController::class, 'search'])->name('clients.search');
    Route::get('clients/export', [Admin\ClientController::class, 'export'])->name('clients.export');
    Route::resource('clients', Admin\ClientController::class);

    // Valuation objects
    $objects = [
        'real-estates' => Admin\RealEstateController::class,
        'assets' => Admin\AssetController::class,
        'vehicles' => Admin\VehicleController::class,
        'other-assets' => Admin\OtherAssetController::class,
        'damage-cases' => Admin\DamageCaseController::class,
    ];
    foreach ($objects as $uri => $controller) {
        Route::get("{$uri}/export", [$controller, 'export'])->name("{$uri}.export");
        Route::get("{$uri}/{id}/print", [$controller, 'print'])->whereNumber('id')->name("{$uri}.print");
        Route::resource($uri, $controller)->parameters([$uri => 'id']);
    }

    // Damage catalogues
    $catalogs = [
        'car-models' => Admin\CarModelController::class,
        'spare-parts' => Admin\SparePartController::class,
        'small-parts' => Admin\SmallPartController::class,
        'repair-norms' => Admin\RepairNormController::class,
    ];
    foreach ($catalogs as $uri => $controller) {
        Route::get("{$uri}/lookup", [$controller, 'lookup'])->name("{$uri}.lookup");
        Route::get("{$uri}/export", [$controller, 'export'])->name("{$uri}.export");
        Route::post("{$uri}/import", [$controller, 'import'])->middleware('can:manage-catalogs')->name("{$uri}.import");
        Route::resource($uri, $controller)->except('show')->parameters([$uri => 'id']);
    }

    // "Baholash plus" — valuation calculators
    Route::get('tools', Admin\ToolsController::class)->name('tools');

    // Website requests
    Route::resource('leads', Admin\LeadController::class)->only(['index', 'show', 'update', 'destroy']);

    // Attachments
    Route::post('attachments', [Admin\AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('attachments/{attachment}', [Admin\AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('attachments/{attachment}', [Admin\AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('districts', Admin\DistrictLookupController::class)->name('districts');

    Route::get('profile', [Admin\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [Admin\ProfileController::class, 'update'])->middleware('throttle:10,1')->name('profile.update');

    // Administrators only
    Route::middleware('can:admin')->group(function () {
        Route::resource('users', Admin\UserController::class)->except('show');
        Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/currency', [Admin\SettingController::class, 'refreshRates'])->name('settings.currency');
        Route::get('activity', Admin\ActivityController::class)->name('activity.index');
        Route::get('regions', [Admin\RegionController::class, 'index'])->name('regions.index');
        Route::post('regions', [Admin\RegionController::class, 'store'])->name('regions.store');
        Route::put('regions/{region}', [Admin\RegionController::class, 'update'])->name('regions.update');
        Route::post('regions/{region}/districts', [Admin\RegionController::class, 'storeDistrict'])->name('regions.districts.store');
        Route::put('districts/{district}', [Admin\RegionController::class, 'updateDistrict'])->name('districts.update');
        Route::delete('districts/{district}', [Admin\RegionController::class, 'destroyDistrict'])->name('districts.destroy');
    });
});
