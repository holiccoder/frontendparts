import tailwindUrl from '@tailwindcss/browser?url';
import * as esbuild from 'esbuild-wasm';
import wasmUrl from 'esbuild-wasm/esbuild.wasm?url';

import type { LiveEditFrameworkPayload } from '@/types/catalog';

import { instrumentReactSource } from './live-edit-instrument-react';
import { OUTLINE_RUNTIME_SCRIPT } from './live-edit-outline-runtime';

/**
 * Live edit runtime (SPEC §5.6, §2.5; Phase 3.1) — LAZY-LOADED chunk.
 *
 * This module is only reached through a dynamic `import()` from the Edit
 * tab, so esbuild-wasm and @tailwindcss/browser never land in the main
 * bundle. The wasm binary is self-hosted: Vite emits it as a content-hashed
 * static asset (`?url`) and it is fetched once, on first Edit click.
 *
 * Pipeline per rebuild (keystrokes never touch the server):
 *  1. esbuild-wasm bundles the edited closure sources from a virtual FS
 *     (authored relative imports resolve verbatim against the payload paths)
 *  2. bare package imports stay EXTERNAL, rewritten to esm.sh URLs at the
 *     registry-pinned versions — the browser fetches them as native ESM,
 *     so the render uses the exact dep versions the prebuilt previews bundle
 *  3. the bundle mounts the component with its sample data inside a
 *     sandboxed iframe; @tailwindcss/browser compiles Tailwind v4 there
 *     (same `@custom-variant dark` as the library app, so the dark toggle
 *     behaves like the prebuilt previews)
 *
 * Phase 3.3: the onLoad step runs the SAME data-fp-* attribute injection
 * the preview build applies server-side (live-edit-instrument-react), and
 * the frame runtime answers the SPEC §5.3 highlight/clear protocol — so
 * structure-tree outlines keep working in edit mode.
 */

const ENTRY_POINT = 'fp-entry.tsx';
const VIRTUAL_NAMESPACE = 'fp-live-edit';

/* React majors mirror package.json: the edited tree renders with the same
 * React generation as the host app and the prebuilt previews. esm.sh
 * resolves the range once and every module (bundle + deps) shares the
 * single pinned instance via `?deps=`. */
const REACT_SPEC = 'react@^19';
const REACT_DOM_SPEC = 'react-dom@^19';
const ESM_SH = 'https://esm.sh/';

export interface LiveEditUpdate {
    /** Virtual file map: library-relative path → edited source. */
    files: Map<string, string>;
    /** Raw JSON text of the entry component's edited sample data. */
    dataText: string;
}

export interface LiveEditSession {
    /** Rebuild the bundle and swap the iframe document. Coalesced: a build
     * requested mid-flight replaces the queued one. Rejects on compile errors. */
    update(input: LiveEditUpdate): Promise<void>;
    /** Push the dark/light theme into the live iframe (SPEC §5.3 theme message). */
    setDark(dark: boolean): void;
    /** Outline a component's rendered region(s) — structure-tree hover (Phase 3.3). */
    highlight(slug: string, instance: number | null): void;
    /** Remove all outlines. */
    clearHighlight(): void;
    dispose(): void;
}

export interface LiveEditSessionOptions {
    payload: LiveEditFrameworkPayload;
    iframe: HTMLIFrameElement;
    dark: boolean;
    onHeight: (px: number) => void;
    onRuntimeError: (message: string) => void;
}

/* ------------------------------------------------------------------------ */
/* esbuild-wasm singleton                                                    */
/* ------------------------------------------------------------------------ */

let esbuildReady: Promise<unknown> | null = null;

function initializeEsbuild(): Promise<unknown> {
    esbuildReady ??= esbuild.initialize({
        wasmURL: new URL(wasmUrl, document.baseURI).href,
        worker: true,
    });

    return esbuildReady;
}

/* ------------------------------------------------------------------------ */
/* Virtual file system                                                       */
/* ------------------------------------------------------------------------ */

function normalizePath(path: string): string {
    const segments: string[] = [];

    for (const segment of path.split('/')) {
        if (segment === '' || segment === '.') {
            continue;
        }

        if (segment === '..') {
            segments.pop();
            continue;
        }

        segments.push(segment);
    }

    return segments.join('/');
}

function dirname(path: string): string {
    const index = path.lastIndexOf('/');

    return index === -1 ? '' : path.slice(0, index);
}

/** Mirrors CompositionGraph::resolveFile (SPEC §2.2): literal path first,
 * then .tsx / .ts extensions, then the index form. */
function resolveFile(base: string, files: Map<string, string>): string | null {
    for (const candidate of [base, `${base}.tsx`, `${base}.ts`, `${base}/index.tsx`, `${base}/index.ts`]) {
        if (files.has(candidate)) {
            return candidate;
        }
    }

    return null;
}

/**
 * Bare specifiers → esm.sh URLs (SPEC §2.5). Registry deps resolve at their
 * pinned versions; transitive React is pinned via `?deps=` so hooks share
 * the bundle's single React instance. Off-registry specifiers (should not
 * happen — sync rejects them) fall back to an unpinned esm.sh URL rather
 * than failing the session.
 */
function esmUrl(specifier: string, deps: Record<string, string | null>): string {
    const withPinnedReact = (url: string): string => `${url}?deps=${encodeURIComponent(REACT_SPEC)},${encodeURIComponent(REACT_DOM_SPEC)}`;

    if (specifier === 'react' || specifier.startsWith('react/')) {
        return `${ESM_SH}${REACT_SPEC}${specifier.slice('react'.length)}`;
    }

    if (specifier === 'react-dom' || specifier.startsWith('react-dom/')) {
        return `${ESM_SH}${REACT_DOM_SPEC}${specifier.slice('react-dom'.length)}`;
    }

    for (const pinned of Object.values(deps)) {
        if (!pinned) {
            continue;
        }

        const at = pinned.lastIndexOf('@');

        if (at < 0) {
            continue;
        }

        const pkg = pinned.slice(0, at);

        if (specifier === pkg || specifier.startsWith(`${pkg}/`)) {
            return withPinnedReact(`${ESM_SH}${pinned}${specifier.slice(pkg.length)}`);
        }
    }

    return withPinnedReact(`${ESM_SH}${specifier}`);
}

function virtualFsPlugin(payload: LiveEditFrameworkPayload, files: Map<string, string>, dataText: string): esbuild.Plugin {
    const moduleFiles = new Map(files);
    moduleFiles.set(`${payload.entry}/data.json`, dataText);

    return {
        name: 'fp-live-edit',
        setup(build: esbuild.PluginBuild) {
            build.onResolve({ filter: /.*/ }, (args) => {
                if (args.path === ENTRY_POINT) {
                    return { path: ENTRY_POINT, namespace: VIRTUAL_NAMESPACE };
                }

                if (args.path.startsWith('.')) {
                    const importerDir = args.importer === ENTRY_POINT ? '' : dirname(args.importer);
                    const resolved = resolveFile(normalizePath(`${importerDir}/${args.path}`), moduleFiles);

                    if (resolved !== null) {
                        return { path: resolved, namespace: VIRTUAL_NAMESPACE };
                    }

                    return {
                        errors: [{ text: `Cannot resolve "${args.path}" — it is not part of this component's closure.` }],
                    };
                }

                return { path: esmUrl(args.path, payload.deps), external: true };
            });

            build.onLoad({ filter: /.*/, namespace: VIRTUAL_NAMESPACE }, (args) => {
                if (args.path === ENTRY_POINT) {
                    return {
                        contents: instrumentReactSource(
                            [
                                `import { createRoot } from 'react-dom/client';`,
                                `import Component from './${payload.entry}/index';`,
                                `import data from './${payload.entry}/data.json';`,
                                ``,
                                `createRoot(document.getElementById('root')!).render(<Component {...data} />);`,
                            ].join('\n'),
                            '',
                        ),
                        loader: 'tsx',
                    };
                }

                const code = moduleFiles.get(args.path);

                if (code === undefined) {
                    return { errors: [{ text: `Missing file in edit payload: ${args.path}` }] };
                }

                /* Phase 3.3: every TSX module gets the same data-fp-*
                 * injection the preview build applies server-side, so the
                 * structure tree's outlines map onto the live render. The
                 * module's virtual path doubles as the importer dir for
                 * relative-import resolution. */
                return {
                    contents: args.path.endsWith('.json') ? code : instrumentReactSource(code, dirname(args.path)),
                    loader: args.path.endsWith('.json') ? 'json' : 'tsx',
                };
            });
        },
    };
}

async function buildBundle(payload: LiveEditFrameworkPayload, files: Map<string, string>, dataText: string): Promise<string> {
    await initializeEsbuild();

    const result = await esbuild.build({
        entryPoints: [ENTRY_POINT],
        bundle: true,
        write: false,
        format: 'esm',
        jsx: 'automatic',
        target: 'es2022',
        logLevel: 'silent',
        plugins: [virtualFsPlugin(payload, files, dataText)],
    });

    return result.outputFiles[0].text;
}

/* ------------------------------------------------------------------------ */
/* Sandboxed live iframe                                                     */
/* ------------------------------------------------------------------------ */

/**
 * Iframe protocol (SPEC §5.3 in edit mode): theme + structure-tree
 * highlight/clear in; fp-edit-ready, fp-edit-height and fp-edit-error out.
 * The outline paint/restore logic is the same script the prebuilt previews
 * inline (live-edit-outline-runtime). Written without template literals so
 * it inlines safely.
 */
const FRAME_RUNTIME = [
    '(function () {',
    "    'use strict';",
    '    function post(message) {',
    '        if (window.parent && window.parent !== window) {',
    "            window.parent.postMessage(message, '*');",
    '        }',
    '    }',
    '    function reportHeight() {',
    "        post({ type: 'fp-edit-height', px: Math.ceil(document.documentElement.scrollHeight) });",
    '    }',
    "    window.addEventListener('error', function (event) {",
    "        post({ type: 'fp-edit-error', message: String(event.message || 'Preview script error') });",
    '    });',
    "    window.addEventListener('unhandledrejection', function (event) {",
    "        post({ type: 'fp-edit-error', message: String((event.reason && event.reason.message) || event.reason || 'Preview script error') });",
    '    });',
    "    window.addEventListener('message', function (event) {",
    '        var data = event.data;',
    "        if (data && typeof data === 'object' && data.type === 'theme') {",
    "            document.documentElement.classList.toggle('dark', data.mode === 'dark');",
    '        }',
    '    });',
    OUTLINE_RUNTIME_SCRIPT,
    "    window.addEventListener('load', function () {",
    "        post({ type: 'fp-edit-ready' });",
    '        reportHeight();',
    '    });',
    "    if ('ResizeObserver' in window) {",
    '        new ResizeObserver(reportHeight).observe(document.documentElement);',
    '    }',
    '})();',
].join('\n');

function frameDocument(bundle: string, dark: boolean): string {
    /* Keep the inline module from breaking out of its own script tag. */
    const script = bundle.replace(/<\/script/gi, '<\\/script');
    const tailwindSrc = new URL(tailwindUrl, document.baseURI).href;

    return [
        '<!doctype html>',
        `<html lang="en"${dark ? ' class="dark"' : ''}>`,
        '<head>',
        '<meta charset="utf-8">',
        '<meta name="viewport" content="width=device-width, initial-scale=1">',
        `<script src="${tailwindSrc}"></script>`,
        '<style type="text/tailwindcss">',
        "@import 'tailwindcss';",
        '',
        '/* Same class-based dark variant as the library app (SPEC §5.3). */',
        '@custom-variant dark (&:where(.dark, .dark *));',
        '</style>',
        '</head>',
        '<body>',
        '<div id="root"></div>',
        '<script>',
        FRAME_RUNTIME,
        '</script>',
        '<script type="module">',
        script,
        '</script>',
        '</body>',
        '</html>',
    ].join('\n');
}

/* ------------------------------------------------------------------------ */
/* Session                                                                   */
/* ------------------------------------------------------------------------ */

export async function createLiveEditSession(options: LiveEditSessionOptions): Promise<LiveEditSession> {
    const { payload, iframe, onHeight, onRuntimeError } = options;
    let dark = options.dark;
    let disposed = false;
    let building = false;
    let queued: LiveEditUpdate | null = null;

    const onMessage = (event: MessageEvent) => {
        if (event.source !== iframe.contentWindow) {
            return;
        }

        const data = event.data as { type?: unknown; px?: unknown; message?: unknown } | null;

        if (!data || typeof data !== 'object') {
            return;
        }

        if (data.type === 'fp-edit-height' && typeof data.px === 'number') {
            onHeight(Math.min(Math.max(Math.ceil(data.px), 160), 5000));
        } else if (data.type === 'fp-edit-error') {
            onRuntimeError(typeof data.message === 'string' ? data.message : 'Preview script error');
        }
    };

    window.addEventListener('message', onMessage);

    /* Compile once up front so a broken initialize (missing wasm, no WebAssembly
     * support) rejects session creation instead of the first keystroke. */
    await initializeEsbuild();

    return {
        async update(input: LiveEditUpdate): Promise<void> {
            if (disposed) {
                return;
            }

            if (building) {
                queued = input;
                return;
            }

            building = true;

            try {
                let current: LiveEditUpdate | undefined = input;

                while (current !== undefined && !disposed) {
                    const bundle = await buildBundle(payload, current.files, current.dataText);
                    iframe.srcdoc = frameDocument(bundle, dark);
                    current = queued ?? undefined;
                    queued = null;
                }
            } finally {
                building = false;
            }
        },

        setDark(nextDark: boolean): void {
            dark = nextDark;
            iframe.contentWindow?.postMessage({ type: 'theme', mode: dark ? 'dark' : 'light' }, '*');
        },

        highlight(slug: string, instance: number | null): void {
            iframe.contentWindow?.postMessage({ type: 'highlight', slug, instance }, '*');
        },

        clearHighlight(): void {
            iframe.contentWindow?.postMessage({ type: 'clear' }, '*');
        },

        dispose(): void {
            disposed = true;
            queued = null;
            window.removeEventListener('message', onMessage);
        },
    };
}

/** Format an esbuild BuildFailure (or anything else) for the error banner. */
export function formatBuildError(error: unknown): string {
    const errors = (error as { errors?: Array<{ text: string; location?: { file: string; line: number; column: number } | null }> })?.errors;

    if (Array.isArray(errors) && errors.length > 0) {
        const first = errors[0];
        const where = first.location ? ` (${first.location.file}:${first.location.line}:${first.location.column})` : '';

        return `${first.text}${where}`;
    }

    return error instanceof Error ? error.message : String(error);
}
