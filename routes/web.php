<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Site;
use App\Services\Analogs\AnalogFinder;
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
        Route::get("{$uri}/{id}/contract", [$controller, 'contract'])->whereNumber('id')->name("{$uri}.contract");
        Route::post("{$uri}/{id}/workflow", [$controller, 'workflow'])->whereNumber('id')->name("{$uri}.workflow");
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
    // Analogues of the comparative approach
    Route::controller(Admin\AnalogController::class)->group(function () {
        $types = array_keys(AnalogFinder::OBJECTS);
        Route::post('{type}/{id}/analogs', 'store')->whereIn('type', $types)->whereNumber('id')->middleware('throttle:20,1')->name('analogs.store');
        Route::put('{type}/{id}/analogs', 'update')->whereIn('type', $types)->whereNumber('id')->name('analogs.update');
        Route::post('{type}/{id}/analogs/apply', 'apply')->whereIn('type', $types)->whereNumber('id')->name('analogs.apply');
        Route::post('analogs/{analog}/fill', 'fill')->middleware('throttle:60,1')->name('analogs.fill');
        Route::delete('analogs/{analog}', 'destroy')->name('analogs.destroy');
        Route::get('analogs/{analog}/photo', 'showPhoto')->name('analogs.photo');
        Route::post('analogs/{analog}/photo', 'photo')->middleware('throttle:30,1')->name('analogs.photo.store');
        Route::delete('analogs/{analog}/photo', 'deletePhoto')->name('analogs.photo.destroy');
    });

    // Full valuation report of real estate
    Route::controller(Admin\ValuationReportController::class)->group(function () {
        Route::get('real-estates/{id}/report', 'show')->whereNumber('id')->name('real-estates.report');
        Route::get('real-estates/{id}/report/edit', 'edit')->whereNumber('id')->name('real-estates.report.edit');
        Route::put('real-estates/{id}/report', 'update')->whereNumber('id')->name('real-estates.report.update');
        Route::get('report-documents/{index}', 'document')->whereNumber('index')->name('report-documents.show');
    });
    Route::controller(Admin\DocumentReadController::class)->group(function () {
        Route::post('real-estates/{id}/documents/read', 'read')->whereNumber('id')->middleware('throttle:10,1')->name('real-estates.documents.read');
        Route::get('real-estates/{id}/documents/review', 'review')->whereNumber('id')->name('real-estates.documents.review');
        Route::post('real-estates/{id}/documents/apply', 'apply')->whereNumber('id')->name('real-estates.documents.apply');
    });

    Route::get('tools', Admin\ToolsController::class)->name('tools');

    // Website requests
    Route::resource('leads', Admin\LeadController::class)->only(['index', 'show', 'update', 'destroy']);

    // Attachments
    Route::post('attachments', [Admin\AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('attachments/{attachment}', [Admin\AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('attachments/{attachment}', [Admin\AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::patch('attachments/{attachment}', [Admin\AttachmentController::class, 'update'])->name('attachments.update');
    Route::post('attachments/{attachment}/rotate', [Admin\AttachmentController::class, 'rotate'])->middleware('throttle:60,1')->name('attachments.rotate');

    Route::get('districts', Admin\DistrictLookupController::class)->name('districts');

    Route::get('profile', [Admin\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [Admin\ProfileController::class, 'update'])->middleware('throttle:10,1')->name('profile.update');

    // Administrators only
    Route::middleware('can:admin')->group(function () {
        Route::resource('users', Admin\UserController::class)->except('show');
        Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/currency', [Admin\SettingController::class, 'refreshRates'])->name('settings.currency');
        Route::post('settings/documents', [Admin\SettingController::class, 'storeDocuments'])->name('settings.documents.store');
        Route::delete('settings/documents/{index}', [Admin\SettingController::class, 'destroyDocument'])->whereNumber('index')->name('settings.documents.destroy');
        Route::get('report-texts', [Admin\ReportTextController::class, 'edit'])->name('report-texts.edit');
        Route::put('report-texts', [Admin\ReportTextController::class, 'update'])->name('report-texts.update');
        Route::get('activity', [Admin\ActivityController::class, 'index'])->name('activity.index');
        Route::get('activity/export', [Admin\ActivityController::class, 'export'])->name('activity.export');
        Route::post('activity/verify', [Admin\ActivityController::class, 'verify'])->middleware('throttle:6,1')->name('activity.verify');
        Route::get('system', [Admin\SystemController::class, 'index'])->name('system.index');
        Route::delete('system/sessions/{session}', [Admin\SystemController::class, 'endSession'])->name('system.sessions.destroy');
        Route::get('regions', [Admin\RegionController::class, 'index'])->name('regions.index');
        Route::post('regions', [Admin\RegionController::class, 'store'])->name('regions.store');
        Route::put('regions/{region}', [Admin\RegionController::class, 'update'])->name('regions.update');
        Route::post('regions/{region}/districts', [Admin\RegionController::class, 'storeDistrict'])->name('regions.districts.store');
        Route::put('districts/{district}', [Admin\RegionController::class, 'updateDistrict'])->name('districts.update');
        Route::delete('districts/{district}', [Admin\RegionController::class, 'destroyDistrict'])->name('districts.destroy');
    });
});
