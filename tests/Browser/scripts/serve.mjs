/**
 * Dev server for the browser suite (task 5.7), managed by Playwright's
 * webServer lifecycle: Playwright starts this script before the run and
 * kills it afterwards. We spawn the SAME single-process server
 * `php artisan serve` uses (the Foundation server.php router) so the process
 * tree stays flat — node → php — and no grandchildren can leak.
 *
 * On any shutdown signal we additionally taskkill the PHP child (Windows
 * does not deliver SIGTERM to grandchildren), then re-raise so Playwright's
 * own teardown still applies. Nothing is detached; nothing is backgrounded.
 */
import { spawn, spawnSync } from 'node:child_process';
import path from 'node:path';
import { browserEnv, host, phpBinary, port, repoRoot, serverRouter } from './env.mjs';

const php = phpBinary();

const child = spawn(php, ['-S', `${host}:${port}`, serverRouter], {
    cwd: path.join(repoRoot, 'public'),
    env: browserEnv(),
    stdio: 'inherit',
});

let stopping = false;

function stopPhp() {
    if (stopping || child.pid === undefined) {
        return;
    }

    stopping = true;

    if (process.platform === 'win32') {
        spawnSync('taskkill', ['/pid', String(child.pid), '/T', '/F'], { stdio: 'ignore' });
    } else {
        try {
            child.kill('SIGTERM');
        } catch {
            // already gone
        }
    }
}

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
        stopPhp();
        process.exit(0);
    });
}

process.on('exit', stopPhp);

child.on('error', (error) => {
    console.error(`[browser-serve] failed to start PHP (${php}): ${error.message}`);
    process.exit(1);
});

child.on('exit', (code) => {
    process.exit(stopping ? 0 : (code ?? 1));
});
