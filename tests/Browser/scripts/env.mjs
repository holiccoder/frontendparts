/**
 * Shared paths and env for the browser-test runner (task 5.7).
 *
 * The suite serves the app in the dedicated `browser` environment
 * (.env.browser) against a disposable sqlite FILE (database/browser.sqlite)
 * — never the dev database, never the in-memory PHPUnit one. PHP is not on
 * PATH on every machine, so resolution is explicit:
 * FP_PHP_BINARY → Herd per-user install → bare `php` (PATH).
 */
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));

export const repoRoot = path.resolve(here, '..', '..', '..');

export const host = '127.0.0.1';
export const port = Number(process.env.FP_BROWSER_PORT ?? 8899);
export const baseURL = `http://${host}:${port}`;

export const dbPath = path.join(repoRoot, 'database', 'browser.sqlite');

/** The same router script `php artisan serve` uses (Illuminate Foundation). */
export const serverRouter = path.join(repoRoot, 'vendor', 'laravel', 'framework', 'src', 'Illuminate', 'Foundation', 'resources', 'server.php');

/**
 * Process env for any PHP child (fixtures setup, dev server): forces the
 * browser environment and the absolute sqlite path, and strips undefined
 * values so the object is spawn-safe.
 *
 * @param {Record<string, string | undefined>} [extra]
 * @returns {Record<string, string>}
 */
export function browserEnv(extra = {}) {
    /** @type {Record<string, string>} */
    const env = {};

    for (const [key, value] of Object.entries({
        ...process.env,
        APP_ENV: 'browser',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: dbPath,
        ...extra,
    })) {
        if (value !== undefined) {
            env[key] = String(value);
        }
    }

    return env;
}

/**
 * Resolve a PHP CLI binary: FP_PHP_BINARY override first, then the Herd
 * per-user install (Windows), then a bare `php` (PATH lookup).
 *
 * @returns {string}
 */
export function phpBinary() {
    if (process.env.FP_PHP_BINARY && existsSync(process.env.FP_PHP_BINARY)) {
        return process.env.FP_PHP_BINARY;
    }

    if (process.platform === 'win32' && process.env.USERPROFILE) {
        const herd = path.join(process.env.USERPROFILE, '.config', 'herd', 'bin', 'php84', 'php.exe');

        if (existsSync(herd)) {
            return herd;
        }
    }

    return 'php';
}
