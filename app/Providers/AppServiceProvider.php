<?php

namespace App\Providers;

use App\Listeners\SendWelcomeNotification;
use App\Support\Settings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Paddle\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);

        // The app runs its own order state machine (SPEC §7.3) on
        // POST /paddle/webhook instead of Cashier's webhook controller.
        Cashier::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Registered::class, SendWelcomeNotification::class);
    }
}
