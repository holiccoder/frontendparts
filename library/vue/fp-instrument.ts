import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { ElementTypes, NodeTypes } from '@vue/compiler-core';
import type { AttributeNode, NodeTransform } from '@vue/compiler-core';

/**
 * Preview-build instrumentation (SPEC §2.3).
 *
 * A `@vue/compiler-dom` node transform (wired via `@vitejs/plugin-vue`
 * template options) injecting `data-fp-c="{child-full-slug}"` +
 * `data-fp-i="{n}"` onto `<ChildComponent/>` tags in SFC templates. Vue's
 * native attribute fall-through lands the attributes on the child's root
 * DOM node.
 *
 * The template AST carries no import statements, so tags resolve through a
 * name → slug map scanned from `src/components` (the authoring convention
 * names every component after its slug: `section-title-01` ⇢ `SectionTitle01`).
 * The instance counter is a per-file static occurrence counter (1-based,
 * traversal order) — deterministic across rebuilds.
 *
 * Only wired into `vite.build.config.ts` when `FP_INSTRUMENT=1`; authored
 * sources and standalone dev builds stay completely clean.
 */

const LEVEL_DIRECTORIES = ['elements', 'blocks', 'sections', 'pages'];

function pascalCase(slug: string): string {
    return slug
        .split('-')
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join('');
}

function buildNameMap(componentsRoot: string): Map<string, string> {
    const map = new Map<string, string>();

    for (const level of LEVEL_DIRECTORIES) {
        const levelDir = path.join(componentsRoot, level);

        if (!fs.existsSync(levelDir)) {
            continue;
        }

        for (const slug of fs.readdirSync(levelDir)) {
            if (!fs.existsSync(path.join(levelDir, slug, 'index.vue'))) {
                continue;
            }

            map.set(pascalCase(slug), `${level}/${slug}`);
        }
    }

    return map;
}

export function fpInstrument(): NodeTransform {
    const componentsRoot = fileURLToPath(new URL('./src/components', import.meta.url));
    const nameToSlug = buildNameMap(componentsRoot);
    const countersByRoot = new WeakMap<object, Map<string, number>>();

    return (node, context) => {
        if (node.type !== NodeTypes.ELEMENT || node.tagType !== ElementTypes.COMPONENT) {
            return;
        }

        const slug = nameToSlug.get(node.tag);

        if (!slug) {
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
