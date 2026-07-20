<?php

use App\Http\Controllers\ComponentPreviewController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/previews/{component}/{version}/{framework}.html', [ComponentPreviewController::class, 'show'])
    ->where('component', '[a-z0-9\-/]+')
    ->where('framework', 'react|vue')
    ->name('previews.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
