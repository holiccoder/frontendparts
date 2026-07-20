export type ComponentLevel = 'element' | 'block' | 'section' | 'page';
export type AccessLevel = 'free' | 'paid';
export type Framework = 'react' | 'vue';

export interface CategoryRef {
    name: string;
    slug: string;
}

export interface ComponentCardData {
    id: number;
    name: string;
    slug: string;
    level: ComponentLevel;
    access: AccessLevel;
    usage: CategoryRef;
    url: string;
    thumb: string | null;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
}

export interface ComponentFile {
    path: string;
    code: string;
}

/** Live edit (SPEC §5.6): one virtual source file of the edit payload. */
export type LiveEditFile = ComponentFile;

/** Live edit (SPEC §5.6, §2.5): per-framework client-bundler payload. */
export interface LiveEditFrameworkPayload {
    /** Full slug of the component being edited (e.g. `sections/pricing-01`). */
    entry: string;
    /** The full composition closure: parent + children, library-relative paths. */
    files: LiveEditFile[];
    /** Sample-data modules keyed by component slug. */
    data: Record<string, Record<string, unknown>>;
    /** Logical dep → registry-pinned `package@version` for esm.sh. */
    deps: Record<string, string | null>;
}

export interface LiveEditPayload {
    react?: LiveEditFrameworkPayload;
}

export interface TreeNode {
    slug: string;
    basename: string;
    usage: string;
    name: string;
    level: ComponentLevel;
    instances: number;
    children: TreeNode[];
}

export interface ParamDefinition {
    type: string;
    default?: unknown;
    description?: string;
    options?: string[];
}

export interface ComponentDetailData {
    id: number;
    slug: string;
    basename: string;
    name: string;
    level: ComponentLevel;
    usage: CategoryRef;
    industries: CategoryRef[];
    tags: CategoryRef[];
    access: AccessLevel;
    entitled: boolean;
    features: {
        dark_toggle: boolean;
        tree_interactions: boolean;
        live_edit: boolean;
    };
    citation: {
        source_name: string | null;
        source_url: string | null;
    };
    version: string;
    deps: string[];
    params: Record<string, ParamDefinition>;
    data: Record<string, unknown>;
    files: {
        react: ComponentFile[];
        vue: ComponentFile[];
    };
    previews: {
        react: string | null;
        vue: string | null;
    };
    screenshots: {
        react: Record<string, string | null>;
        vue: Record<string, string | null>;
    };
    tree: TreeNode;
    related: ComponentCardData[];
    og_image: string | null;
    /** Present only when features.live_edit is on AND the reader is entitled. */
    edit?: LiveEditPayload;
}

export interface PageMeta {
    title: string;
    description: string;
    canonical: string;
    og_image: string;
    og_type?: string;
    robots?: string;
}

export interface IndustryTile {
    name: string;
    slug: string;
    components_count: number;
    description: string | null;
    url: string;
}

export interface UsageFilter {
    name: string;
    slug: string;
    zone: string | null;
    components_count: number;
}

export interface IndustryFilter {
    name: string;
    slug: string;
    components_count: number;
}
