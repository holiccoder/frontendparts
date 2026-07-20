import type { ComponentType } from 'react';

/**
 * Component registry.
 *
 * Discovers every component under `src/components/{level}/{slug}/` via
 * `import.meta.glob` and pairs each `index.tsx` with its `data.json`.
 * The full slug is the path relative to `components/`
 * (e.g. `elements/section-title-01`).
 *
 * This module is shared by the standalone `/preview/{slug}` route and,
 * later, by the preview build pipeline — one renderer, no duplication.
 */

export type ComponentData = Record<string, unknown>;

export interface RegistryEntry {
    /** Full slug relative to `src/components/`, e.g. `elements/section-title-01`. */
    slug: string;
    /** Granularity level: elements | blocks | sections | pages. */
    level: string;
    /** Leaf slug, e.g. `section-title-01`. */
    name: string;
    component: ComponentType<ComponentData>;
    data: ComponentData;
}

const componentModules = import.meta.glob<{ default: ComponentType<ComponentData> }>('../components/**/index.tsx', {
    eager: true,
});

const dataModules = import.meta.glob<ComponentData>('../components/**/data.json', {
    eager: true,
    import: 'default',
});

/** `../components/elements/section-title-01/index.tsx` → `elements/section-title-01` */
function slugFromPath(path: string): string {
    return path.replace(/^\.\.\/components\//, '').replace(/\/index\.tsx$/, '').replace(/\/data\.json$/, '');
}

function buildRegistry(): Map<string, RegistryEntry> {
    const registry = new Map<string, RegistryEntry>();

    for (const [path, module] of Object.entries(componentModules)) {
        const slug = slugFromPath(path);
        const [level, name] = slug.split('/');

        if (!level || !name) {
            continue;
        }

        const dataPath = `../components/${slug}/data.json`;
        const data = dataModules[dataPath] ?? {};

        registry.set(slug, {
            slug,
            level,
            name,
            component: module.default,
            data,
        });
    }

    return registry;
}

const registry = buildRegistry();

/** All discovered components, sorted by slug. */
export function listComponents(): RegistryEntry[] {
    return [...registry.values()].sort((a, b) => a.slug.localeCompare(b.slug));
}

/** Resolve a full slug (`elements/section-title-01`) to its registry entry. */
export function resolveComponent(slug: string): RegistryEntry | null {
    return registry.get(slug) ?? null;
}
