---
title: Customizing components
description: Make a component yours — editing Tailwind utilities directly, retheming accent colors with Tailwind 4 @theme tokens, and dark mode.
---

# Customizing components

Downloaded components are plain `.tsx` / `.vue` files in your repo — there is no theming API to learn and no wrapper fighting your changes. Customization is just editing Tailwind utilities, with a few conventions that make bulk changes easy.

## The styling conventions

Components are styled with **Tailwind CSS 4 utilities only**:

- **Neutrals for structure** — `text-neutral-900` headings, `text-neutral-600` body copy, `border-neutral-200` hairlines.
- **One accent family per component** — typically `indigo-600` for eyebrows, links and primary buttons.
- **Responsive prefixes** (`sm:`, `lg:`) already tuned at phone, tablet and desktop widths — the same widths the preview screenshots use.

Because everything is a utility class, every change is local and greppable.

## Small tweaks: edit the classes

Change spacing, type size or alignment directly where the class lives:

```tsx
// before
<h2 className="text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl">

// after — bigger on desktop
<h2 className="text-3xl font-bold tracking-tight text-neutral-900 sm:text-5xl">
```

The code is yours — no override is too small to just write.

## Retheming: swap the accent palette

To move a component from the default indigo accent to your brand color, replace the accent utilities. Tailwind 4 makes this a two-step job you do once per project.

Define your brand scale in your CSS entrypoint with `@theme`:

```css
@import 'tailwindcss';

@theme {
    --color-brand-600: #0d9488;
    --color-brand-700: #0f766e;
}
```

Then swap the accent classes in the component files — a project-wide find-and-replace of `indigo-` → `brand-` inside the copied `components/` folder covers most cases:

```tsx
// before
<span className="text-sm font-semibold tracking-widest text-indigo-600 uppercase">

// after
<span className="text-sm font-semibold tracking-widest text-brand-600 uppercase">
```

Keep the numeric shade (`600` → `600`) and hover states keep working unchanged.

### Token quick reference

Everything a component references is a stock Tailwind 4 token, so a full retheme is one `@theme` block away:

| Role in components | Utilities used | Token family to override |
|---|---|---|
| Headings | `text-neutral-900` | `--color-neutral-900` |
| Body copy | `text-neutral-600` | `--color-neutral-600` |
| Muted labels | `text-neutral-400` / `text-neutral-500` | `--color-neutral-400`, `--color-neutral-500` |
| Hairlines, card borders | `border-neutral-200` | `--color-neutral-200` |
| Accent (eyebrows, links, primary buttons) | `text-indigo-600`, `bg-indigo-600`, `hover:bg-indigo-700` | your brand scale, e.g. `--color-brand-*` |
| Surfaces | `bg-white`, `bg-neutral-50` | `--color-neutral-50` or a surface token |

Override the neutral tokens themselves to shift the whole grayscale warm or cool; define a new accent family (as above) to change the brand color without touching neutrals. Type and spacing come from Tailwind's default scale — override `--font-sans` to re-font every copied component at once:

```css
@theme {
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
}
```

## Dark mode

Components ship light-first. Where a dark treatment exists it uses Tailwind's `dark:` variant, which in Tailwind 4 you control with the custom variant in your CSS:

```css
@custom-variant dark (&:is(.dark *));
```

Add your own `dark:` utilities next to the light ones (`dark:bg-neutral-900`, `dark:text-neutral-100`) as you need them — utilities compose, so a dark pass over a copied component is additive and safe to do file by file.

## Layout changes

Sections are ordinary flex/grid markup. Common operations:

- **Remove a part** — delete the element and, if it came from a data slice, its entry in the data module.
- **Reorder children** — move the JSX; data slices for repeated children are positional arrays (see [Params & data](/docs/using-components/params-and-data)).
- **Change max width / gutters** — look for the `max-w-*` and `px-*` utilities on the outermost element.

## Staying upgrade-friendly

If you want to pull a newer version of a component later, keep your edits concentrated:

1. Restyle through classes rather than restructuring markup where possible.
2. Keep content in the data module instead of inline props.
3. Note local changes in a short comment header per file.

Then re-downloading and diffing the zip against your copy is a quick review instead of a rewrite.

Back to: [Params & data](/docs/using-components/params-and-data) · [Install for React](/docs/install/react) · [Install for Vue](/docs/install/vue).
