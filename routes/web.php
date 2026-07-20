<?php

use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\CheckoutSuccessController;
use App\Http\Controllers\Billing\PaddleWebhookController;
use App\Http\Controllers\Billing\PricingController;
use App\Http\Controllers\Billing\ReactivateOrderController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BlogFeedController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ComponentApiController;
use App\Http\Controllers\ComponentController;
use App\Http\Controllers\ComponentCopyController;
use App\Http\Controllers\ComponentDownloadController;
use App\Http\Controllers\ComponentPreviewController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\OrdersController;
use App\Http\Controllers\Dashboard\TicketController;
use App\Http\Controllers\Dashboard\TicketMessageController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IndustryController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Projects\ProjectComponentController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\ProjectExportController;
use App\Http\Controllers\Projects\ProjectExportDownloadController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;
use Laravel\Paddle\Http\Middleware\VerifyWebhookSignature;

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

Route::get('/docs', [DocsController::class, 'index'])->name('docs.index');

Route::get('/docs/search', [DocsController::class, 'search'])->name('docs.search');

Route::get('/docs/{section}/{page}', [DocsController::class, 'show'])
    ->where('section', '[a-z0-9\-]+')
    ->where('page', '[a-z0-9\-]+')
    ->name('docs.show');

Route::get('/pricing', PricingController::class)->name('pricing');

Route::get('/search', SearchController::class)->name('search');

/*
| Legal pages (SPEC §15.7, §15.1): seven SSR, SEO-indexed pages rendered by
| one controller from markdown in resources/legal/. The footer links the
| full set from every public page; `/affiliate-terms` ships with the
| affiliate program at P2 (§17.7).
*/
Route::get('/terms', [LegalController::class, 'show'])->defaults('page', 'terms')->name('legal.terms');
Route::get('/privacy', [LegalController::class, 'show'])->defaults('page', 'privacy')->name('legal.privacy');
Route::get('/license', [LegalController::class, 'show'])->defaults('page', 'license')->name('legal.license');
Route::get('/refund-policy', [LegalController::class, 'show'])->defaults('page', 'refund-policy')->name('legal.refund-policy');
Route::get('/cookie-policy', [LegalController::class, 'show'])->defaults('page', 'cookie-policy')->name('legal.cookie-policy');
Route::get('/copyright', [LegalController::class, 'show'])->defaults('page', 'copyright')->name('legal.copyright');
Route::get('/legal-notice', [LegalController::class, 'show'])->defaults('page', 'legal-notice')->name('legal.legal-notice');

/*
| Blog (SPEC §13.1, §15.1): index, article and category pages plus the RSS
| feed. Feed and category routes are declared before the article slug so
| `/blog/feed` and `/blog/category/…` are never captured as a post slug.
*/
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');

Route::get('/blog/feed', BlogFeedController::class)->name('blog.feed');

Route::get('/blog/category/{slug}', [BlogController::class, 'category'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('blog.category');

Route::get('/blog/{slug}', [BlogController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('blog.show');

/*
|--------------------------------------------------------------------------
| One-click unsubscribe (SPEC §16.3)
|--------------------------------------------------------------------------
|
| Carried by every marketing email. Signature-authenticated instead of
| session-authenticated so it works logged-out; performs the unsubscribe
| in a single GET and confirms. Not indexed.
|
*/

Route::get('/unsubscribe/{user}', UnsubscribeController::class)
    ->middleware(['signed', 'noindex'])
    ->name('unsubscribe');

/*
|--------------------------------------------------------------------------
| Reactivation link (SPEC §16.2 B7)
|--------------------------------------------------------------------------
|
| Carried by the cancellation confirmation and Day 7 / Day 30 followup
| mails. Signature-authenticated so it works from a mail client; forwards
| to checkout for the cancelled order's plan (a cancelled Paddle
| subscription is re-bought, not un-cancelled). Not indexed.
|
*/

Route::get('/billing/reactivate/{order}', ReactivateOrderController::class)
    ->middleware(['signed', 'noindex'])
    ->name('billing.reactivate');

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
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    /*
    |----------------------------------------------------------------------
    | Orders (SPEC §7.3, §15.4): order history with Paddle receipt/invoice
    | URLs, license state and renewal dates.
    |----------------------------------------------------------------------
    */
    Route::get('dashboard/orders', OrdersController::class)->name('dashboard.orders.index');

    /*
    |----------------------------------------------------------------------
    | Projects (SPEC §6.1, §15.4): list/detail pages, CRUD, component-set
    | add/remove (JSON for the catalog "Add to project" UI, redirects for
    | the Inertia pages) and the queued pack-zip export (SPEC §6.2).
    |----------------------------------------------------------------------
    */
    Route::prefix('dashboard/projects')->name('dashboard.projects.')->group(function () {
        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::post('/', [ProjectController::class, 'store'])->name('store');
        Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
        Route::patch('/{project}', [ProjectController::class, 'update'])->name('update');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');
        Route::post('/{project}/components', [ProjectComponentController::class, 'store'])->name('components.store');
        Route::delete('/{project}/components/{component}', [ProjectComponentController::class, 'destroy'])->name('components.destroy');
        Route::post('/{project}/export', ProjectExportController::class)->name('export');
        Route::get('/{project}/export/{export}/download', ProjectExportDownloadController::class)
            ->scopeBindings()
            ->name('export.download');
    });

    /*
    |----------------------------------------------------------------------
    | Support tickets (SPEC §13.3, §15.4): list, create (rate-limited per
    | NFR-10), threaded replies and user-close. Users only ever see and
    | touch their own tickets (owner check in the controllers).
    |----------------------------------------------------------------------
    */
    Route::prefix('dashboard/tickets')->name('dashboard.tickets.')->group(function () {
        Route::get('/', [TicketController::class, 'index'])->name('index');
        Route::get('/new', [TicketController::class, 'create'])->name('create');
        Route::post('/', [TicketController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('store');
        Route::get('/{ticket}', [TicketController::class, 'show'])->name('show');
        Route::patch('/{ticket}', [TicketController::class, 'update'])->name('update');
        Route::post('/{ticket}/messages', [TicketMessageController::class, 'store'])->name('messages.store');
    });

    /*
    |----------------------------------------------------------------------
    | Checkout zone (CSR, noindex — SPEC §15.3)
    |----------------------------------------------------------------------
    */
    Route::get('checkout/success', CheckoutSuccessController::class)->name('checkout.success');

    Route::get('checkout/{plan}', CheckoutController::class)
        ->where('plan', 'starter|pro')
        ->name('checkout.show');
});

/*
|--------------------------------------------------------------------------
| Paddle webhooks (SPEC §7.3)
|--------------------------------------------------------------------------
|
| Signature-verified by Cashier's middleware (HMAC-SHA256 over `ts:body`
| with the webhook secret) and CSRF-exempt (bootstrap/app.php); idempotent
| on replayed event ids.
|
*/

Route::post('paddle/webhook', PaddleWebhookController::class)
    ->middleware(VerifyWebhookSignature::class)
    ->name('paddle.webhook');

Route::middleware(['ssr.skip', 'noindex'])->group(function () {
    require __DIR__.'/settings.php';
});

Route::middleware(['noindex'])->group(function () {
    require __DIR__.'/auth.php';
});
