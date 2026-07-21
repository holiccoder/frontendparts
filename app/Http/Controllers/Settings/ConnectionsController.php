<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Notifications\GithubConnectedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;

/**
 * Connected accounts (SPEC §6.4): the `/settings/connections` page plus the
 * GitHub OAuth handshake — redirect with the `repo` scope, callback storing
 * the access token encrypted (model cast) and queueing the security notice
 * (SPEC §16.1), and disconnect clearing the stored token.
 */
class ConnectionsController extends Controller
{
    public function edit(Request $request): Response
    {
        // Query the relation (not the cached property): tests reuse the
        // actingAs user instance across requests, and a stale cached null
        // would hide a connection created between page loads.
        $connection = $request->user()->githubConnection()->first();

        return Inertia::render('settings/connections', [
            'github' => [
                'connected' => $connection !== null,
                'login' => $connection?->github_login,
                'connected_at' => $connection?->created_at->toIso8601String(),
                'urls' => [
                    'connect' => route('connections.github.redirect'),
                    'disconnect' => route('connections.github.destroy'),
                ],
            ],
        ]);
    }

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')->scopes(['repo'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        $request->user()->githubConnection()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'github_id' => (string) $githubUser->getId(),
                'github_login' => $githubUser->getNickname() ?? (string) $githubUser->getName(),
                'token' => $githubUser->token,
            ],
        );

        $request->user()->notify(new GithubConnectedNotification($githubUser->getNickname() ?? (string) $githubUser->getName()));

        return to_route('connections.edit')->with('notice', 'GitHub account connected — exports can now be pushed to your repositories.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->githubConnection()?->delete();

        return back()->with('notice', 'GitHub account disconnected.');
    }
}
