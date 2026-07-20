<?php

use App\Http\Controllers\Settings\BillingController;
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

    Route::patch('settings/preview-layout', [PreviewLayoutController::class, 'update'])
        ->middleware('verified')
        ->name('settings.preview-layout');
});
