# FrontendParts Component Library (authoring workspace)

This is where every catalog component is **authored**: two standalone Vite apps —
`react/` and `vue/` — plus a shared dependency registry. Both apps live in the main
repo (git-versioned) but are **not** npm workspaces of the root app; each has its own
`package.json` and `node_modules` (SPEC §8.1).

The same component slug in both apps = the same component, two implementations.
A component is publishable only when both twins exist and render with visual parity.

## Folder conventions

```
library/
├── deps.registry.json       ← approved external deps (see below)
├── react/                   ← standalone Vite + React 19 + TS + Tailwind 4 app
│   └── src/components/{level}/{slug}/
│       ├── index.tsx        ← annotated component source
│       ├── params.json      ← prop schema + defaults (contract)
│       └── data.json        ← realistic sample content (showcase)
└── vue/                     ← standalone Vite + Vue 3 + TS + Tailwind 4 app
    └── src/components/{level}/{slug}/
        ├── index.vue
        ├── params.json
        └── data.json
```

- **Levels:** `elements`, `blocks`, `sections`, `pages`.
- **Full slug** = `{level}/{slug}` (e.g. `elements/section-title-01`).
- `params.json` (SPEC §3.1): every prop has `{type, default, description}`; types from
  `string · text · number · boolean · enum(options) · image · url · array<T> · object{…}`.
  Every prop must have a default so the component renders standalone.
- `data.json`: realistic sample values overriding the defaults
  (resolution order: props passed in → `data.json` → param defaults).

## Annotation metadata (source of truth)

Every `index.tsx` / `index.vue` carries a docblock at the top (SPEC §8.2):

```
/**
 * @component  section-title-01
 * @name       Section Title 01
 * @level      element
 * @usage      feature-grid
 * @industries saas, fintech        (may be empty)
 * @tags       minimal, typography
 * @access     free | pro
 * @source     https://example.com  (site it visually resembles)
 * @deps       lucide               (logical names only, never versions; may be empty)
 * @version    1.0.0
 */
```

## Standalone preview

Each app serves component previews without any router dependency
(SPEC §8.4 — same resolution logic is reused by the preview build pipeline):

```bash
cd library/react && npm install && npm run dev     # http://localhost:5173
cd library/vue   && npm install && npm run dev
```

- `/` — index page listing all discovered component slugs
- `/preview/{level}/{slug}` — mounts the component with its `data.json`,
  e.g. `/preview/elements/section-title-01`
- unknown slugs render a plain-text error page

Resolution lives in `src/lib/registry.ts` in each app (`import.meta.glob` over
`src/components/**/index.{tsx,vue}` + `data.json`).

## Composition: imports become child edges

The composition graph is **derived from code, never declared by hand** (SPEC §2.2).
`library:sync` statically parses the ES `import` statements in each `index.tsx` /
`index.vue`:

- Relative imports (`./…`, `../…`) resolve against the importing file, and the `@/`
  alias resolves to the app's `src/` directory.
- An import that resolves **into another component directory** (a `{level}/{slug}`
  folder containing `params.json`) registers a **child edge** — e.g. from
  `sections/pricing-section-01/index.tsx`, `import PricingCard from
  '../../blocks/pricing-card-01'` makes `blocks/pricing-card-01` a child.
- npm packages, CSS, and anything outside `src/components` are ignored.

Hard rules enforced at sync time:

- **No cycles** — `A → B → A` fails the import with the cycle path in the error.
- **Max nesting depth 10** (root = depth 1); an 11-deep chain is rejected.
- **Shared children are deduplicated** — two parents importing the same slug
  reference one component record.

## Preview instrumentation (`data-fp-*`)

Preview builds (SPEC §5.2) inject `data-fp-c="{child-slug}"` + `data-fp-i="{n}"`
onto child component instances at build time (SPEC §2.3) so the modal's structure
tree can outline them. **Authoring convention:** every component forwards unknown
props/attributes to its root DOM node (React: `{...rest}` spread, shadcn-style;
Vue: native attribute fall-through), so the injected attributes land on the
child's root element. Authored sources never contain the attributes — the
transform only runs for preview builds (`FP_INSTRUMENT=1` via
`vite.build.config.ts`).

## Composition data: the `children` slice convention

A composite's `data.json` may carry a reserved `children` key mapping **child slug →
object or array of objects** (SPEC §3.3). Code passes each slice down as props, and
`library:sync` validates every slice against the child's own `params.json` schema —
a mismatch fails the import naming the child and the offending param:

```json
{
    "heading": "Simple, transparent pricing",
    "children": {
        "pricing-card-01": [
            { "plan": "Starter", "price": 9 },
            { "plan": "Pro", "price": 29 }
        ]
    }
}
```

## Dependency registry (`deps.registry.json`)

SPEC §2.5 three-tier policy: components are zero-dep by default (React/Vue + Tailwind
only, earns a "zero-dep" badge). Approved utility deps must exist in **both**
ecosystems — a dep with no equivalent in the other framework blocks publishing.

The registry maps a **logical name** to the per-framework package + pinned version:

```json
{
    "lucide": {
        "react": "lucide-react@^1.25.0",
        "vue": "lucide-vue-next@^1.0.0"
    }
}
```

Rules:

- Annotations (`@deps`) name **logical packages only, never versions**.
- Versions are pinned caret ranges resolved from what is actually installed —
  never `"latest"` or `"*"`.
- Both library apps install every approved package (`dependencies` of each app).
- `library:sync` fails components whose `@deps` are not in the registry, and
  rejects off-allowlist deps unless explicitly approved.
