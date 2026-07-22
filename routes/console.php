<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lifecycle email engine (SPEC §16.2): drip steps are anchored to exact
// calendar days, so the command must run once a day; every send is
// idempotent, making a re-run within the same day harmless.
Schedule::command('mail:run-sequences')->daily();

// Affiliate commission release (SPEC §17.1 step 5): pending commissions
// become payable once the refund window + holding period have elapsed.
Schedule::command('affiliates:mark-payable')->daily();

// Daily database backup to the configured `backups` disk (SPEC §4.2.3).
Schedule::command('db:backup')->dailyAt('03:00');
