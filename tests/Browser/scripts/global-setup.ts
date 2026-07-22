/**
 * Playwright global setup — runs ONCE per `playwright test` invocation,
 * before the webServer boots:
 *
 *  1. sanity-checks the app build (public/build/manifest.json — the
 *     `test:browser` npm script chains `npm run build` for exactly this),
 *  2. drops a stale public/hot marker (a leftover vite-dev marker would
 *     hijack every asset URL),
 *  3. rebuilds the disposable browser.sqlite database through
 *     `migrate:fresh --seed` so the smoke spec runs against realistic data.
 */
import { execFileSync } from 'node:child_process';
import { existsSync, rmSync } from 'node:fs';
import path from 'node:path';
import { browserEnv, dbPath, phpBinary, repoRoot } from './env.mjs';

export default async function globalSetup(): Promise<void> {
    if (!existsSync(path.join(repoRoot, 'public', 'build', 'manifest.json'))) {
        throw new Error('public/build/manifest.json is missing — run the suite via `npm run test:browser` (it chains `npm run build`).');
    }

    const hotFile = path.join(repoRoot, 'public', 'hot');

    if (existsSync(hotFile)) {
        rmSync(hotFile);
        console.log('[browser-setup] removed stale public/hot marker');
    }

    for (const suffix of ['', '-wal', '-shm']) {
        rmSync(`${dbPath}${suffix}`, { force: true });
    }

    console.log('[browser-setup] rebuilding browser.sqlite via migrate:fresh --seed…');

    execFileSync(phpBinary(), ['artisan', 'migrate:fresh', '--seed', '--no-interaction'], {
        cwd: repoRoot,
        env: browserEnv(),
        stdio: 'inherit',
    });

    console.log('[browser-setup] ready');
}
