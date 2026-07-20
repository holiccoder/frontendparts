import path from 'node:path';
import { fileURLToPath } from 'node:url';
import type { PluginObj, PluginPass } from '@babel/core';
import type { JSXAttribute } from '@babel/types';

/**
 * Preview-build instrumentation (SPEC §2.3).
 *
 * Injects `data-fp-c="{child-full-slug}"` + `data-fp-i="{n}"` onto every JSX
 * element whose tag resolves — via the file's own import statements — to
 * another library component (`src/components/{level}/{slug}`). The instance
 * counter `n` is a per-file static occurrence counter (1-based, traversal
 * order), so it is deterministic across rebuilds.
 *
 * Children forward unknown props to their root DOM node (authoring
 * convention), so the attributes land on the child's root element.
 *
 * Only wired into `vite.build.config.ts` when `FP_INSTRUMENT=1`; authored
 * sources and standalone dev builds stay completely clean.
 */

const COMPONENTS_ROOT = fileURLToPath(new URL('./src/components', import.meta.url)).replace(/\\/g, '/');

const LEVEL_DIRECTORIES = ['elements', 'blocks', 'sections', 'pages'];

interface FpState extends PluginPass {
    fpImports?: Map<string, string>;
    fpCounters?: Map<string, number>;
}

export default function fpInstrument(): PluginObj<FpState> {
    return {
        name: 'fp-instrument-attributes',
        visitor: {
            Program: {
                enter(programPath, state) {
                    const imports = new Map<string, string>();

                    for (const statement of programPath.node.body) {
                        if (statement.type !== 'ImportDeclaration') {
                            continue;
                        }

                        const source = statement.source.value;

                        if (!source.startsWith('.')) {
                            continue;
                        }

                        const slug = resolveComponentSlug(path.dirname(state.filename ?? ''), source);

                        if (slug === null) {
                            continue;
                        }

                        for (const specifier of statement.specifiers) {
                            imports.set(specifier.local.name, slug);
                        }
                    }

                    state.fpImports = imports;
                    state.fpCounters = new Map();
                },
            },
            JSXOpeningElement(elementPath, state) {
                const tag = elementPath.node.name;

                if (tag.type !== 'JSXIdentifier') {
                    return;
                }

                const slug = state.fpImports?.get(tag.name);

                if (!slug) {
                    return;
                }

                const counters = state.fpCounters ?? new Map<string, number>();
                state.fpCounters = counters;

                const instance = (counters.get(slug) ?? 0) + 1;
                counters.set(slug, instance);

                elementPath.node.attributes.push(
                    jsxAttribute('data-fp-c', slug),
                    jsxAttribute('data-fp-i', String(instance)),
                );
            },
        },
    };
}

function jsxAttribute(name: string, value: string): JSXAttribute {
    return {
        type: 'JSXAttribute',
        name: { type: 'JSXIdentifier', name },
        value: { type: 'StringLiteral', value },
    };
}

/**
 * Resolve a relative import to a component full slug (`{level}/{slug}`),
 * or null when the import is not a library component.
 */
function resolveComponentSlug(fromDir: string, importSource: string): string | null {
    const resolved = path.resolve(fromDir, importSource).replace(/\\/g, '/');

    for (const candidate of [resolved, resolved.replace(/\/index(\.tsx?)?$/, '')]) {
        if (!candidate.startsWith(`${COMPONENTS_ROOT}/`)) {
            continue;
        }

        const relative = candidate.slice(COMPONENTS_ROOT.length + 1);
        const segments = relative.split('/');

        if (segments.length === 2 && LEVEL_DIRECTORIES.includes(segments[0])) {
            return relative;
        }
    }

    return null;
}
