<?php

namespace App\Providers;

use App\Services\Sequences\CancelFollowupSequence;
use App\Services\Sequences\DunningSequence;
use App\Services\Sequences\FreeOnboardingSequence;
use App\Services\Sequences\NewDropsDigestSequence;
use App\Services\Sequences\PaidOnboardingSequence;
use App\Services\Sequences\RenewalReminderSequence;
use App\Services\Sequences\SequenceRegistry;
use App\Services\Sequences\UpgradeTriggerSequence;
use App\Support\Settings;
use Illuminate\Contracts\Foundation\Application;
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

        // Lifecycle sequences (SPEC §16.2) — one entry per sequence; B8
        // registers here when implemented.
        $this->app->singleton(SequenceRegistry::class, fn (Application $app): SequenceRegistry => new SequenceRegistry([
            $app->make(FreeOnboardingSequence::class),
            $app->make(UpgradeTriggerSequence::class),
            $app->make(PaidOnboardingSequence::class),
            $app->make(NewDropsDigestSequence::class),
            $app->make(RenewalReminderSequence::class),
            $app->make(DunningSequence::class),
            $app->make(CancelFollowupSequence::class),
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
        // Note: listeners under app/Listeners are auto-discovered
        // (Application::configure defaults withEvents() to discovery), so
        // SendWelcomeNotification and LogNotificationSent (SPEC §16.3) need
        // no explicit registration — adding one would fire them twice.
    }
}
