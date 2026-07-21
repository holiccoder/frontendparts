/**
 * Playwright global setup (task 5.7) — runs ONCE per `playwright test`
 * invocation, before the webServer boots:
 *
 *  1. sanity-checks the app build (public/build/manifest.json — the
 *     `test:browser` npm script chains `npm run build` for exactly this),
 *  2. drops a stale public/hot marker (a leftover vite-dev marker would
 *     hijack every asset URL),
 *  3. rebuilds the disposable browser.sqlite fixture set through
 *     `php artisan browser:fixtures` — real library sync + REAL vite preview
 *     builds for the composite fixture component (sections/hero-01),
 *  4. pre-bundles local ESM copies of react / react-dom for the esm.sh
 *     shim the live-edit spec installs (tests/Browser/scripts/esm-shims.ts),
 *     so the in-browser compiler surface stays hermetic and offline-proof.
 */
import { build } from 'esbuild';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { browserEnv, dbPath, phpBinary, repoRoot } from './env.mjs';

const cacheDir = path.join(repoRoot, 'tests', 'Browser', '.cache');

/** ESM entry shims bundled offline with the repo's own esbuild + node_modules. */
const SHIMS = {
    'react.mjs': ["import React from 'react';", "export * from 'react';", 'export default React;'].join('\n'),
    'react-jsx-runtime.mjs': ["export { Fragment, jsx, jsxs } from 'react/jsx-runtime';"].join('\n'),
    'react-dom-client.mjs': ["export { createRoot, hydrateRoot } from 'react-dom/client';"].join('\n'),
};

async function buildEsmShims(): Promise<void> {
    const srcDir = path.join(cacheDir, 'shims-src');
    const outDir = path.join(cacheDir, 'esm');

    mkdirSync(srcDir, { recursive: true });
    mkdirSync(outDir, { recursive: true });

    for (const [outfile, source] of Object.entries(SHIMS)) {
        const entry = path.join(srcDir, outfile.replace('.mjs', '.js'));

        writeFileSync(entry, source);

        await build({
            entryPoints: [entry],
            outfile: path.join(outDir, outfile),
            bundle: true,
            format: 'esm',
            platform: 'browser',
            logLevel: 'silent',
            absWorkingDir: repoRoot,
        });
    }
}

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

    console.log('[browser-setup] seeding fixtures via php artisan browser:fixtures (real library sync + real preview builds)…');

    execFileSync(phpBinary(), ['artisan', 'browser:fixtures'], {
        cwd: repoRoot,
        env: browserEnv(),
        stdio: 'inherit',
    });

    await buildEsmShims();

    console.log('[browser-setup] ready');
}
