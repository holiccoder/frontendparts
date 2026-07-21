<?php

use App\Http\Controllers\Settings\BillingController;
use App\Http\Controllers\Settings\ConnectionsController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\PreviewLayoutController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');

    Route::get('settings/notifications', [NotificationPreferenceController::class, 'edit'])->name('notifications.edit');
    Route::patch('settings/notifications', [NotificationPreferenceController::class, 'update'])->name('notifications.update');

    /*
    |----------------------------------------------------------------------
    | Billing settings (SPEC §15.4, §16.2): the canonical update-payment
    | deep-link target for dunning mail (B6), plus the B7 cancel flow —
    | required exit survey → reason-mapped save offer → confirm.
    |----------------------------------------------------------------------
    */
    Route::get('settings/billing', [BillingController::class, 'edit'])->name('settings.billing');
    Route::post('settings/billing/cancel', [BillingController::class, 'cancel'])->name('settings.billing.cancel');

    /*
    |----------------------------------------------------------------------
    | Connected accounts (SPEC §6.4): the connections page plus the GitHub
    | OAuth handshake (`repo` scope). The callback URL must match the GitHub
    | OAuth app's authorization callback (GITHUB_REDIRECT_URL).
    |----------------------------------------------------------------------
    */
    Route::get('settings/connections', [ConnectionsController::class, 'edit'])->name('connections.edit');
    Route::get('settings/connections/github/redirect', [ConnectionsController::class, 'redirect'])->name('connections.github.redirect');
    Route::get('settings/connections/github/callback', [ConnectionsController::class, 'callback'])->name('connections.github.callback');
    Route::delete('settings/connections/github', [ConnectionsController::class, 'destroy'])->name('connections.github.destroy');

    Route::patch('settings/preview-layout', [PreviewLayoutController::class, 'update'])
        ->middleware('verified')
        ->name('settings.preview-layout');
});
