---
title: Params & data
description: The two-file model behind every component — params.json as the contract, data.json as showcase content, and the props → data.json → defaults resolution order.
---

# Params & data

Every FrontendParts component is driven by two JSON files. They are what make a component render standalone in the preview, generate its own props table on the Docs tab, and accept your content without code changes.

| File | Role | Contents |
|---|---|---|
| `params.json` | Schema + defaults — the contract | Name, type, default, description and constraints per param |
| `data.json` | Sample content — the showcase | Realistic demo values (texts, image URLs) overriding defaults |

Only `data.json` ships in the download zip (as a typed module under `data/`); `params.json` stays platform-side to power previews and docs.

## The params.json contract

A real example — the `section-title-01` element:

```json
{
    "eyebrow": {
        "type": "string",
        "default": "",
        "description": "Small label displayed above the heading. Hidden when empty."
    },
    "align": {
        "type": "enum",
        "options": ["left", "center"],
        "default": "center",
        "description": "Horizontal alignment of the whole block."
    }
}
```

Every param declares a **type**, a **default** and a **description**; `enum` params add an `options` list. The Docs tab on each component page is generated from this file, so the props table you read always matches the code.

The type vocabulary is deliberately small and JSON-serializable:

| Type | Use |
|---|---|
| `string` | Short text (labels, headings) |
| `text` | Long-form copy (paragraphs) |
| `number` | Counts, prices, ratings |
| `boolean` | Toggles |
| `enum` | One of a fixed `options` list |
| `image` | Image URL |
| `url` | Link URL |
| `array<T>` | Lists of any of the above |
| `object{…}` | Nested structures (e.g. child slices) |

Non-serializable params (callbacks, render props) never appear in the JSON files — they are documented on the Docs tab and default to no-ops in standalone mode.

## data.json — showcase content

`data.json` overrides defaults with realistic content, so the preview looks like a finished site instead of placeholder text:

```json
{
    "children": {
        "section-title-01": [
            {
                "eyebrow": "Features",
                "heading": "Everything you need to ship faster",
                "align": "center"
            }
        ]
    }
}
```

In the download zip each component's `data.json` becomes an importable TypeScript module:

```ts
// data/title-showcase-01.ts
export default {
    children: {
        'section-title-01': [
            /* … */
        ],
    },
} as const;
```

## Resolution order

Values resolve in a strict, three-step order:

**props you pass → `data.json` → param defaults**

- Pass a prop and it always wins.
- Without a prop, the `data.json` value is used.
- Without either, the param default kicks in — and since every param must have a default, **every component renders independently**, at any level, with no wiring. That standalone-render property is also the QA gate: a component that cannot display on its own cannot be published.

In practice this means you can drop a section in with zero props to see it, then replace content field by field.

## Composites: children slices

Composites keep the same model. Each child keeps its own `params.json` + `data.json`, and the parent's `data.json` mirrors its children under the `children` key — slices keyed by child slug, passed down as props:

```tsx
// sections/TitleShowcase01.tsx (simplified)
const [first = {}, second = {}] = children?.['section-title-01'] ?? [];

return (
    <div className="flex flex-col gap-8 px-6 py-16 sm:gap-12">
        <SectionTitle01 {...first} />
        <SectionTitle01 {...second} />
    </div>
);
```

When the library syncs, composite data slices are validated against the child schemas — a mismatch fails the import, so published data is always consistent with the contract.

## Overriding data in your project

Two equally valid styles:

1. **Edit the data module** — keep content out of your JSX and diff-friendly. Best for sections and pages with lots of copy.
2. **Pass props inline** — best for one-off overrides on elements and blocks:

```tsx
<SectionTitle01 heading="Pricing that scales with you" />
```

Either way the resolution order guarantees the rest of the component keeps working.

Next: [Customizing the look with Tailwind](/docs/using-components/customizing).
