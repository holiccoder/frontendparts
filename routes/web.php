<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ComponentApiController;
use App\Http\Controllers\ComponentController;
use App\Http\Controllers\ComponentCopyController;
use App\Http\Controllers\ComponentDownloadController;
use App\Http\Controllers\ComponentPreviewController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IndustryController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public zone (SSR, SEO-indexed — SPEC §10.1, §15.1)
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/components', [CatalogController::class, 'index'])->name('components.index');

Route::get('/components/{usage}', [CatalogController::class, 'usage'])
    ->where('usage', '[a-z0-9\-]+')
    ->name('components.usage');

Route::get('/components/{usage}/{slug}', [ComponentController::class, 'show'])
    ->where('usage', '[a-z0-9\-]+')
    ->where('slug', '[a-z0-9\-]+')
    ->name('components.show');

Route::get('/components/{usage}/{slug}/download', ComponentDownloadController::class)
    ->where('usage', '[a-z0-9\-]+')
    ->where('slug', '[a-z0-9\-]+')
    ->middleware('throttle:10,1')
    ->name('components.download');

Route::post('/components/{usage}/{slug}/copy', ComponentCopyController::class)
    ->where('usage', '[a-z0-9\-]+')
    ->where('slug', '[a-z0-9\-]+')
    ->middleware('throttle:30,1')
    ->name('components.copy');

Route::get('/industries', [IndustryController::class, 'index'])->name('industries.index');

Route::get('/industries/{industry}', [IndustryController::class, 'show'])
    ->where('industry', '[a-z0-9\-]+')
    ->name('industries.show');

/*
|--------------------------------------------------------------------------
| Infrastructure (SPEC §15.6)
|--------------------------------------------------------------------------
*/

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/robots.txt', RobotsController::class)->name('robots');

Route::get('/previews/{component}/{version}/shots/{file}', [ComponentPreviewController::class, 'shot'])
    ->where('component', '[a-z0-9\-/]+')
    ->where('file', '(react|vue)-\d+\.png')
    ->name('previews.shots');

Route::get('/previews/{component}/{version}/{framework}.html', [ComponentPreviewController::class, 'show'])
    ->where('component', '[a-z0-9\-/]+')
    ->where('framework', 'react|vue')
    ->name('previews.show');

/*
|--------------------------------------------------------------------------
| JSON payload for the preview-modal overlay (SPEC §5.4)
|--------------------------------------------------------------------------
|
| Lives on the stateful web stack so an authenticated reader keeps their
| session (the resource's `entitled` placeholder reads the auth user).
|
*/

Route::get('/api/components/{usage}/{slug}', [ComponentApiController::class, 'show'])
    ->where('usage', '[a-z0-9\-]+')
    ->where('slug', '[a-z0-9\-]+')
    ->middleware('throttle:60,1')
    ->name('api.components.show');

/*
|--------------------------------------------------------------------------
| User dashboard zone (CSR, auth, noindex — SPEC §10.1, §15.4)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'ssr.skip', 'noindex'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

Route::middleware(['ssr.skip', 'noindex'])->group(function () {
    require __DIR__.'/settings.php';
});

Route::middleware(['noindex'])->group(function () {
    require __DIR__.'/auth.php';
});
