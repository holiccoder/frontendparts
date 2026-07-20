import tailwindUrl from '@tailwindcss/browser?url';
/* Aliased: @vue/repl's `use*` store factories trip the react-hooks eslint
 * rule, which reserves that naming for React hooks — these are Vue's. */
import {
    File,
    Repl,
    useStore as createReplStore,
    useVueImportMap as createVueImportMap,
    mergeImportMap,
    type ImportMap,
    type Store,
} from '@vue/repl';
import CodeMirrorEditor from '@vue/repl/codemirror-editor';
/* Same package the server-side transform (library/vue/fp-instrument.ts)
 * uses; enum values are plain numbers, so the Repl's own compiler copy
 * compares identically. */
import { ElementTypes, NodeTypes, type AttributeNode, type NodeTransform } from '@vue/compiler-core';
import { computed, createApp, h, ref, type App as VueApp } from 'vue';

import type { LiveEditFile, LiveEditVuePayload } from '@/types/catalog';

import { OUTLINE_RUNTIME_SCRIPT } from './live-edit-outline-runtime';

/**
 * Live edit runtime — Vue (SPEC §5.6, §2.5; Phase 3.2) — LAZY-LOADED chunk.
 *
 * This module is only reached through a dynamic `import()` from the Vue
 * Edit tab, so vue, @vue/repl and its CodeMirror editor never land in the
 * main bundle. Structure mirrors the React runtime (live-edit-runtime):
 *
 *  1. the payload's flat `src/{PascalName}.vue` map seeds the Repl store,
 *     plus two synthesized modules: the `src/App.vue` wrapper mounting the
 *     entry SFC with its sample data (mirrors PreviewBuilder's
 *     `createApp({ render: () => h(Component, data) })`) and an editable
 *     `src/data.ts` (same `export default … as const` form as the zip
 *     export — the Repl has no JSON module support, so the data editor
 *     edits TS)
 *  2. the Repl compiles the SFCs in-browser (vue/compiler-sfc, bundled)
 *     and renders them in its sandboxed iframe; keystrokes never touch
 *     the server
 *  3. bare imports resolve via the preview import map: the Vue runtime
 *     comes from the Repl's builtin map pinned to the HOST vue version
 *     (same generation as the prebuilt previews), registry deps come from
 *     esm.sh at their pinned versions with `?external=vue` so they share
 *     that single Vue instance
 *  4. @tailwindcss/browser compiles Tailwind v4 inside the preview iframe
 *     (same `@custom-variant dark` as the library app, so the dark toggle
 *     behaves like the prebuilt previews); self-hosted via Vite `?url`
 *
 * Phase 3.3: the Repl's SFC compile runs the SAME data-fp-* node transform
 * the preview build applies server-side (via sfcOptions.template
 * .compilerOptions.nodeTransforms — the same hook @vitejs/plugin-vue wires
 * in the library app), and the preview iframe's headHTML carries the
 * SPEC §5.3 highlight/clear runtime — structure-tree outlines keep working
 * in edit mode.
 *
 * The React wrapper (vue-edit-tab) drives download/reset/theme/outlines
 * through the session API; multi-file editing, data editing, compile errors
 * and the preview itself are the Repl's own UI.
 */

/** Editable sample-data module, imported by the generated wrapper. */
const DATA_FILE = 'src/data.ts';

const ESM_SH = 'https://esm.sh/';

export interface VueLiveEditSession {
    /** Current edited sources (repl layout) for the download endpoint. */
    files(): LiveEditFile[];
    /** Push the dark/light theme into the Repl (chrome + preview iframe). */
    setDark(dark: boolean): void;
    /** Outline a component's rendered region(s) — structure-tree hover (Phase 3.3). */
    highlight(slug: string, instance: number | null): void;
    /** Remove all outlines. */
    clearHighlight(): void;
    dispose(): void;
}

export interface VueLiveEditSessionOptions {
    payload: LiveEditVuePayload;
    container: HTMLElement;
    dark: boolean;
}

/** `export default {…} as const` — the zip export's data-module form. */
function dataModule(data: unknown): string {
    return `export default ${JSON.stringify(data, null, 2)} as const;\n`;
}

/** Generated main file: mounts the entry SFC with its sample data. */
function appWrapper(entryFile: string): string {
    const specifier = `./${entryFile.slice('src/'.length)}`;

    return [
        '<script setup lang="ts">',
        `import EntryComponent from '${specifier}';`,
        `import data from './data';`,
        '</script>',
        '',
        '<template>',
        '    <EntryComponent v-bind="data" />',
        '</template>',
        '',
    ].join('\n');
}

/**
 * Registry deps → import-map entries (SPEC §2.5): the exact pinned
 * package@version from esm.sh, `?external=vue` so the package keeps `vue`
 * as a bare specifier and the iframe's import map supplies the single
 * shared Vue runtime the app mounts with. Off-registry deps (should not
 * happen — sync rejects them) are skipped rather than invented.
 */
function depsImportMap(deps: LiveEditVuePayload['deps']): ImportMap {
    const imports: Record<string, string> = {};

    for (const pinned of Object.values(deps)) {
        if (!pinned) {
            continue;
        }

        const at = pinned.lastIndexOf('@');

        if (at <= 0) {
            continue;
        }

        const pkg = pinned.slice(0, at);

        imports[pkg] = `${ESM_SH}${pinned}?external=vue`;
        imports[`${pkg}/`] = `${ESM_SH}${pinned}/`;
    }

    return { imports };
}

/**
 * Client-side port of the preview build's template node transform
 * (library/vue/fp-instrument.ts): injects `data-fp-c="{slug}"` +
 * `data-fp-i="{n}"` onto `<PascalName/>` component tags in SFC templates;
 * Vue's native attribute fall-through lands them on the child's root DOM
 * node. The instance counter is a per-SFC static occurrence counter
 * (1-based, traversal order) — deterministic across rebuilds, identical to
 * the server scheme.
 *
 * The server scans `src/components` for its name → slug map; the edit
 * payload ships the closure's equivalent as `names` (slug → PascalName),
 * inverted here.
 */
function createFpInstrumentTransform(names: LiveEditVuePayload['names']): NodeTransform {
    const nameToSlug = new Map<string, string>();

    for (const [slug, name] of Object.entries(names)) {
        nameToSlug.set(name, slug);
    }

    const countersByRoot = new WeakMap<object, Map<string, number>>();

    return (node, context) => {
        if (node.type !== NodeTypes.ELEMENT || node.tagType !== ElementTypes.COMPONENT) {
            return;
        }

        const slug = nameToSlug.get(node.tag);

        if (slug === undefined) {
            return;
        }

        let counters = countersByRoot.get(context.root);

        if (!counters) {
            counters = new Map();
            countersByRoot.set(context.root, counters);
        }

        const instance = (counters.get(slug) ?? 0) + 1;
        counters.set(slug, instance);

        const attribute = (name: string, content: string): AttributeNode => ({
            type: NodeTypes.ATTRIBUTE,
            name,
            nameLoc: node.loc,
            value: {
                type: NodeTypes.TEXT,
                content,
                loc: node.loc,
            },
            loc: node.loc,
        });

        node.props.push(attribute('data-fp-c', slug), attribute('data-fp-i', String(instance)));
    };
}

/**
 * Preview iframe head (SPEC §5.6): the official Tailwind v4 browser
 * compiler with the same class-based dark variant as the library app, plus
 * the SPEC §5.3 outline runtime so structure-tree hover reaches the live
 * render (Phase 3.3). The Repl regenerates its sandbox srcdoc per compile,
 * so the outline listener reinstalls on every rebuild.
 */
function tailwindHead(): string {
    const src = new URL(tailwindUrl, document.baseURI).href;

    return [
        `<script src="${src}"></script>`,
        '<style type="text/tailwindcss">',
        "@import 'tailwindcss';",
        '',
        '@custom-variant dark (&:where(.dark, .dark *));',
        '</style>',
        '<script>',
        OUTLINE_RUNTIME_SCRIPT,
        '</script>',
    ].join('\n');
}

export async function createVueLiveEditSession(options: VueLiveEditSessionOptions): Promise<VueLiveEditSession> {
    const { payload, container } = options;

    if (payload.entryFile === null || payload.files[payload.entryFile] === undefined) {
        throw new Error('The Vue edit payload has no entry file.');
    }

    const entryFile = payload.entryFile;
    const dark = ref(options.dark);
    let store: Store | null = null;

    const { importMap: vueImportMap } = createVueImportMap();
    const builtinImportMap = computed<ImportMap>(() => mergeImportMap(vueImportMap.value, depsImportMap(payload.deps)));

    const app: VueApp = createApp({
        setup() {
            const files: Record<string, File> = {};

            /* Closure SFCs first (elements → sections order), the data
             * module as the editable data tab. The wrapper itself is NOT
             * seeded here: useStore writes mainFile from
             * template.welcomeSFC when no serialized state is given. */
            for (const [filename, code] of Object.entries(payload.files)) {
                files[filename] = new File(filename, code);
            }

            files[DATA_FILE] = new File(DATA_FILE, dataModule(payload.data[payload.entry] ?? {}));

            store = createReplStore({
                files: ref(files),
                mainFile: ref(payload.mainFile),
                activeFilename: ref(entryFile),
                /* useStore writes mainFile from template.welcomeSFC when no
                 * serialized state is given — that IS our generated wrapper. */
                template: ref({
                    welcomeSFC: appWrapper(entryFile),
                    newSFC: '<script setup lang="ts">\n</script>\n\n<template>\n    <div />\n</template>\n',
                }),
                builtinImportMap,
                /* Phase 3.3: the Repl spreads this into every SFC template
                 * compile — the same compilerOptions.nodeTransforms hook the
                 * library app's @vitejs/plugin-vue build wires server-side. */
                sfcOptions: ref({
                    template: {
                        compilerOptions: {
                            nodeTransforms: [createFpInstrumentTransform(payload.names)],
                        },
                    },
                }),
            });

            return () =>
                h(Repl, {
                    store: store as Store,
                    editor: CodeMirrorEditor,
                    theme: dark.value ? 'dark' : 'light',
                    previewTheme: true,
                    showCompileOutput: false,
                    showImportMap: false,
                    showTsConfig: false,
                    ssr: false,
                    previewOptions: { headHTML: tailwindHead() },
                });
        },
    });

    app.mount(container);

    return {
        files(): LiveEditFile[] {
            if (store === null) {
                return [];
            }

            /* The store synthesizes import-map.json / tsconfig.json — those
             * are Repl plumbing, not user sources, so they stay out of the
             * download. */
            return Object.values(store.files)
                .filter((file) => !file.hidden && file.filename !== 'import-map.json' && file.filename !== 'tsconfig.json')
                .map((file) => ({ path: file.filename, code: file.code }));
        },

        setDark(nextDark: boolean): void {
            dark.value = nextDark;
        },

        /* The Repl owns its preview iframe; reach through the container and
         * use the SPEC §5.3 protocol the headHTML outline runtime answers. */
        highlight(slug: string, instance: number | null): void {
            container.querySelector('iframe')?.contentWindow?.postMessage({ type: 'highlight', slug, instance }, '*');
        },

        clearHighlight(): void {
            container.querySelector('iframe')?.contentWindow?.postMessage({ type: 'clear' }, '*');
        },

        dispose(): void {
            store = null;
            app.unmount();
            container.replaceChildren();
        },
    };
}
