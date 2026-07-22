<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Environment-aware email verification gate (SPEC §15.2).
 *
 * Production keeps Laravel's `verified` behavior; local development can set
 * REQUIRE_EMAIL_VERIFICATION=false to skip the verification notice without
 * touching `User::MustVerifyEmail` or any route definitions. Tests run with
 * the flag at its default (true) unless a test flips the config.
 */
class EnsureEmailIsVerified extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next, $redirectToRoute = null): Response
    {
        if (! config('auth.require_email_verification', true)) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
