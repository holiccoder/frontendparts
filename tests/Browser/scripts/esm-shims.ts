/**
 * esm.sh shim (task 5.7) — the live-edit runtime (SPEC §5.6) deliberately
 * keeps bare package imports external and rewrites them to esm.sh URLs at
 * the registry-pinned versions, so the edited render uses the exact deps the
 * prebuilt previews bundle. In the test environment we intercept those
 * requests and serve offline ESM bundles of the repo's own react / react-dom
 * (pre-built by global-setup into tests/Browser/.cache/esm). The compile
 * under test (esbuild-wasm) and the component sources are entirely real;
 * only the CDN fetch is neutralized — which also keeps CI hermetic.
 */
import type { Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { repoRoot } from './env.mjs';

const shimDir = path.join(repoRoot, 'tests', 'Browser', '.cache', 'esm');

/** Decoded esm.sh pathname → pre-bundled local ESM file. */
const SHIM_BY_PATH = new Map<string, string>([
    ['/react@^19', 'react.mjs'],
    ['/react@^19/jsx-runtime', 'react-jsx-runtime.mjs'],
    ['/react-dom@^19/client', 'react-dom-client.mjs'],
]);

export async function installEsmShims(page: Page): Promise<void> {
    await page.route(/^https:\/\/esm\.sh\//, (route) => {
        const pathname = decodeURIComponent(new URL(route.request().url()).pathname);
        const file = SHIM_BY_PATH.get(pathname);

        if (file === undefined) {
            return route.fulfill({
                status: 404,
                contentType: 'text/plain',
                body: `No esm.sh shim registered for ${pathname} — extend tests/Browser/scripts/esm-shims.ts if a new dep enters the fixture closure.`,
            });
        }

        return route.fulfill({
            status: 200,
            contentType: 'text/javascript',
            body: readFileSync(path.join(shimDir, file)),
        });
    });
}
