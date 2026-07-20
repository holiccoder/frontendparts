<?php

namespace App\Providers;

use App\Listeners\SendWelcomeNotification;
use App\Services\Sequences\FreeOnboardingSequence;
use App\Services\Sequences\NewDropsDigestSequence;
use App\Services\Sequences\PaidOnboardingSequence;
use App\Services\Sequences\SequenceRegistry;
use App\Services\Sequences\UpgradeTriggerSequence;
use App\Support\Settings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Foundation\Application;
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

        // Lifecycle sequences (SPEC §16.2) — one entry per sequence; B5–B8
        // register here when implemented.
        $this->app->singleton(SequenceRegistry::class, fn (Application $app): SequenceRegistry => new SequenceRegistry([
            $app->make(FreeOnboardingSequence::class),
            $app->make(UpgradeTriggerSequence::class),
            $app->make(PaidOnboardingSequence::class),
            $app->make(NewDropsDigestSequence::class),
        ]));

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
