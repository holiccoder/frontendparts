/**
 * FrontendParts dev server (Kimi Work preview entry point).
 *
 * `npm run dev` boots the full local stack and forwards CLI host/port args:
 *
 *   npm run dev -- --port 7100 --host 127.0.0.1
 *
 * Children (all killed on exit — nothing detached, nothing backgrounded):
 *   1. php artisan serve   → the Laravel app on the forwarded host/port
 *   2. vite (node)         → asset dev server with HMR on port 5173
 *   3. php artisan queue:work → pack zips, scaffolds, preview rebuilds, mail
 *
 * PHP is not on PATH on every machine; resolution order:
 *   FP_PHP_BINARY → Herd per-user install (Windows) → bare `php`.
 */
import { spawn, spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

/** Parse --port/--host in both `--flag value` and `--flag=value` forms. */
function arg(name, fallback) {
    const flag = `--${name}`;
    const inline = process.argv.find((a) => a.startsWith(`${flag}=`));

    if (inline) {
        return inline.slice(flag.length + 1);
    }

    const index = process.argv.indexOf(flag);

    return index !== -1 && process.argv[index + 1] ? process.argv[index + 1] : fallback;
}

const host = arg('host', process.env.FP_HOST ?? '127.0.0.1');
const port = Number(arg('port', process.env.FP_PORT ?? 8000));

function phpBinary() {
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

const php = phpBinary();
const viteBin = path.join(repoRoot, 'node_modules', 'vite', 'bin', 'vite.js');

const children = [
    spawn(php, ['artisan', 'serve', `--host=${host}`, `--port=${port}`], { cwd: repoRoot, stdio: 'inherit' }),
    spawn(process.execPath, [viteBin, '--port', '5173'], { cwd: repoRoot, stdio: 'inherit' }),
    spawn(php, ['artisan', 'queue:work', '--tries=1', '--timeout=300'], { cwd: repoRoot, stdio: 'inherit' }),
];

let stopping = false;

function stopAll() {
    if (stopping) {
        return;
    }

    stopping = true;

    for (const child of children) {
        if (child.pid === undefined) {
            continue;
        }

        if (process.platform === 'win32') {
            // Windows does not deliver SIGTERM to grandchildren — taskkill the tree.
            spawnSync('taskkill', ['/pid', String(child.pid), '/T', '/F'], { stdio: 'ignore' });
        } else {
            try {
                child.kill('SIGTERM');
            } catch {
                // already gone
            }
        }
    }
}

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
        stopAll();
        process.exit(0);
    });
}

process.on('exit', stopAll);

for (const child of children) {
    child.on('error', (error) => {
        console.error(`[dev-server] child failed to start: ${error.message}`);
        stopAll();
        process.exit(1);
    });

    child.on('exit', (code) => {
        if (!stopping) {
            console.error(`[dev-server] a child exited (code ${code ?? '?'}) — shutting down the stack`);
            stopAll();
            process.exit(code ?? 1);
        }
    });
}

console.log(`[dev-server] FrontendParts on http://${host}:${port} (vite HMR on 5173, queue worker on)`);
