<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NoIndex;
use App\Http\Middleware\SkipInertiaSsr;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'noindex' => NoIndex::class,
            'ssr.skip' => SkipInertiaSsr::class,
        ]);

        // Paddle webhooks and domestic (Alipay / WeChat) notifies are
        // server-to-server POSTs authenticated by the provider's signature
        // (Paddle-Signature HMAC / RSA2 / WeChat v3), not a session CSRF token.
        $middleware->validateCsrfTokens(except: [
            'paddle/webhook',
            'pay/domestic/alipay/notify',
            'pay/domestic/wechat/notify',
        ]);

        // Live-edit downloads (SPEC §5.6) zip the user's edited sources
        // verbatim — trimming would silently strip edge whitespace (e.g. a
        // file's trailing newline) from the posted code.
        $middleware->trimStrings(except: [
            'files.*.code',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Branded 404 (SPEC §15.1): web requests get the SSR Inertia error
        // page with a link back to the catalog; API/JSON consumers keep the
        // framework's default JSON 404.
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return Inertia::render('error', ['status' => 404])
                ->toResponse($request)
                ->setStatusCode(404);
        });
    })->create();
