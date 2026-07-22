<?php

use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\CheckoutSuccessController;
use App\Http\Controllers\Billing\CurrencySwitchController;
use App\Http\Controllers\Billing\DomesticNotifyController;
use App\Http\Controllers\Billing\DomesticPaymentController;
use App\Http\Controllers\Billing\DomesticPaymentStatusController;
use App\Http\Controllers\Billing\PaddleWebhookController;
use App\Http\Controllers\Billing\PricingController;
use App\Http\Controllers\Billing\ReactivateOrderController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BlogFeedController;
use App\Http\Controllers\Dashboard\AffiliateController;
use App\Http\Controllers\Dashboard\AffiliateJoinController;
use App\Http\Controllers\Dashboard\AffiliatePayoutMethodController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\OrdersController;
use App\Http\Controllers\Dashboard\TeamController;
use App\Http\Controllers\Dashboard\TeamInvitationController;
use App\Http\Controllers\Dashboard\TeamMemberController;
use App\Http\Controllers\Dashboard\TicketController;
use App\Http\Controllers\Dashboard\TicketMessageController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TeamInvitationAcceptController;
use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;
use Laravel\Paddle\Http\Middleware\VerifyWebhookSignature;

/*
|--------------------------------------------------------------------------
| Public zone (SSR, SEO-indexed)
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/docs', [DocsController::class, 'index'])->name('docs.index');

Route::get('/docs/search', [DocsController::class, 'search'])->name('docs.search');

Route::get('/docs/{section}/{page}', [DocsController::class, 'show'])
    ->where('section', '[a-z0-9\-]+')
    ->where('page', '[a-z0-9\-]+')
    ->name('docs.show');

Route::get('/pricing', PricingController::class)->name('pricing');

/*
| Affiliate referral links: `/r/{code}` records the click, stamps the
| 30-day first-party attribution cookie and 301-redirects to pricing;
| unknown or suspended codes silently redirect without recording.
| Rate-limited per IP (fraud control).
*/
Route::get('/r/{code}', ReferralController::class)
    ->middleware('throttle:30,1')
    ->name('affiliate.referral');

/*
| Legal pages: SSR, SEO-indexed pages rendered by one controller from
| markdown in resources/legal/. The footer links the full set from every
| public page; `/affiliate-terms` is linked from the affiliate join flow.
*/
Route::get('/terms', [LegalController::class, 'show'])->defaults('page', 'terms')->name('legal.terms');
Route::get('/privacy', [LegalController::class, 'show'])->defaults('page', 'privacy')->name('legal.privacy');
Route::get('/license', [LegalController::class, 'show'])->defaults('page', 'license')->name('legal.license');
Route::get('/refund-policy', [LegalController::class, 'show'])->defaults('page', 'refund-policy')->name('legal.refund-policy');
Route::get('/cookie-policy', [LegalController::class, 'show'])->defaults('page', 'cookie-policy')->name('legal.cookie-policy');
Route::get('/copyright', [LegalController::class, 'show'])->defaults('page', 'copyright')->name('legal.copyright');
Route::get('/legal-notice', [LegalController::class, 'show'])->defaults('page', 'legal-notice')->name('legal.legal-notice');
Route::get('/affiliate-terms', [LegalController::class, 'show'])->defaults('page', 'affiliate-terms')->name('legal.affiliate-terms');

/*
| Blog: index, article and category pages plus the RSS feed. Feed and
| category routes are declared before the article slug so `/blog/feed`
| and `/blog/category/…` are never captured as a post slug.
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
| One-click unsubscribe
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
| Reactivation link (B7)
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
| Team invitation acceptance
|--------------------------------------------------------------------------
|
| Carried by the invitation email. Signature-authenticated instead of
| session-authenticated so it survives being opened in any browser, but
| auth-gated: guests are bounced through login/registration and back, so
| one link covers existing users and post-registration claims alike.
|
*/

Route::get('team/invitations/{invitation}/accept', TeamInvitationAcceptController::class)
    ->middleware(['auth', 'signed', 'noindex'])
    ->name('team.invitations.accept');

/*
|--------------------------------------------------------------------------
| Infrastructure
|--------------------------------------------------------------------------
*/

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/robots.txt', RobotsController::class)->name('robots');

/*
|--------------------------------------------------------------------------
| User dashboard zone (CSR, auth, noindex)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'ssr.skip', 'noindex'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    /*
    |----------------------------------------------------------------------
    | Orders: order history with Paddle receipt/invoice URLs, license
    | state and renewal dates.
    |----------------------------------------------------------------------
    */
    Route::get('dashboard/orders', OrdersController::class)->name('dashboard.orders.index');

    /*
    |----------------------------------------------------------------------
    | Affiliate program: self-serve join (terms acceptance), overview
    | stats, referral link, commissions, payout history and the
    | payout-method form.
    |----------------------------------------------------------------------
    */
    Route::get('dashboard/affiliate', AffiliateController::class)->name('dashboard.affiliate');
    Route::post('dashboard/affiliate/join', AffiliateJoinController::class)->name('dashboard.affiliate.join');
    Route::put('dashboard/affiliate/payout-method', AffiliatePayoutMethodController::class)
        ->name('dashboard.affiliate.payout-method.update');

    /*
    |----------------------------------------------------------------------
    | Team / organization seats: the owner's management page — members,
    | invitations, removals. Acceptance uses a signed URL and lives
    | outside this group (above) so unverified invitees can claim.
    |----------------------------------------------------------------------
    */
    Route::get('dashboard/team', TeamController::class)->name('dashboard.team');
    Route::post('dashboard/team', [TeamController::class, 'store'])->name('dashboard.team.store');
    Route::post('dashboard/team/invitations', [TeamInvitationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('dashboard.team.invitations.store');
    Route::delete('dashboard/team/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])
        ->name('dashboard.team.invitations.destroy');
    Route::delete('dashboard/team/members/{member}', [TeamMemberController::class, 'destroy'])
        ->name('dashboard.team.members.destroy');

    /*
    |----------------------------------------------------------------------
    | Support tickets: list, create (rate-limited), threaded replies and
    | user-close. Users only ever see and touch their own tickets (owner
    | check in the controllers).
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
        Route::post('/{ticket}/messages', [TicketMessageController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('messages.store');
    });

    /*
    |----------------------------------------------------------------------
    | Checkout zone (CSR, noindex)
    |----------------------------------------------------------------------
    */
    Route::get('checkout/success', CheckoutSuccessController::class)->name('checkout.success');

    Route::get('checkout/{plan}', CheckoutController::class)
        ->where('plan', 'starter|pro|team')
        ->name('checkout.show');

    /*
    |----------------------------------------------------------------------
    | Domestic QR payment (CSR, noindex): QR scan on desktop / app wake-up
    | on mobile, plus the result-polling endpoint the page calls until the
    | order flips Active.
    |----------------------------------------------------------------------
    */
    Route::get('pay/domestic/{order}', DomesticPaymentController::class)
        ->name('pay.domestic');

    Route::get('pay/domestic/{order}/status', DomesticPaymentStatusController::class)
        ->middleware('throttle:30,1')
        ->name('pay.domestic.status');
});

/*
|--------------------------------------------------------------------------
| Manual currency switch
|--------------------------------------------------------------------------
|
| Persists the buyer's USD/CNY choice in the session (guests included) and
| redirects back; RegionDetector prefers it over the geo-detect heuristic.
|
*/

Route::post('billing/currency', CurrencySwitchController::class)
    ->middleware('throttle:10,1')
    ->name('billing.currency.switch');

/*
|--------------------------------------------------------------------------
| Paddle webhooks
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

/*
|--------------------------------------------------------------------------
| Domestic payment notifies
|--------------------------------------------------------------------------
|
| Server-to-server POSTs from Alipay / WeChat Pay, signature-verified at the
| DomesticGateway seam and CSRF-exempt (bootstrap/app.php); idempotent on
| replayed notification ids via domestic_events.
|
*/

Route::post('pay/domestic/{channel}/notify', DomesticNotifyController::class)
    ->where('channel', 'alipay|wechat')
    ->name('pay.domestic.notify');

Route::middleware(['ssr.skip', 'noindex'])->group(function () {
    require __DIR__.'/settings.php';
});

Route::middleware(['noindex'])->group(function () {
    require __DIR__.'/auth.php';
});
