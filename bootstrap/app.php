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
