/**
 * Client-side outline instrumentation — React (SPEC §2.3, §5.6; Phase 3.3).
 *
 * A dependency-free port of the preview build's babel plugin
 * (library/react/babel.fp-instrument.ts): injects
 * `data-fp-c="{child-full-slug}"` + `data-fp-i="{n}"` onto every JSX element
 * whose tag resolves — via the file's own import statements — to another
 * library component. The instance counter `n` is a per-file static
 * occurrence counter (1-based, source order — babel's JSXOpeningElement
 * traversal is pre-order, i.e. source order), deterministic across rebuilds.
 *
 * The virtual edit FS roots every payload file at `{level}/{slug}/index.tsx`,
 * so relative-import resolution mirrors the server plugin's COMPONENTS_ROOT
 * check: a specifier resolving to a two-segment `{level}/{slug}` path (level
 * ∈ elements|blocks|sections|pages, trailing `/index[.tsx]` stripped) maps
 * to that component's slug; everything else is not a library component.
 *
 * The edit runtime has no full TS parser, so injection is a small
 * comment/string/template-aware scanner rather than an AST walk. Documented
 * limits (SPEC §5.6 fallback family): regex literals or template strings
 * CONTAINING JSX-shaped text (`<Capitalized`) could confuse the tag finder —
 * authored library sources don't produce those; a failure would surface as a
 * visible esbuild error, never silent breakage.
 */

const LEVEL_DIRECTORIES = ['elements', 'blocks', 'sections', 'pages'];

/**
 * Resolve a relative import to a component full slug (`{level}/{slug}`),
 * or null when the import is not a library component. Mirrors the server
 * plugin's resolveComponentSlug against the virtual FS root.
 */
function resolveComponentSlug(importerDir: string, specifier: string): string | null {
    if (!specifier.startsWith('.')) {
        return null;
    }

    const segments: string[] = [];

    for (const segment of `${importerDir}/${specifier}`.split('/')) {
        if (segment === '' || segment === '.') {
            continue;
        }

        if (segment === '..') {
            segments.pop();
            continue;
        }

        segments.push(segment);
    }

    const resolved = segments.join('/');

    for (const candidate of [resolved, resolved.replace(/\/index(\.tsx?)?$/, '')]) {
        const parts = candidate.split('/');

        if (parts.length === 2 && LEVEL_DIRECTORIES.includes(parts[0])) {
            return candidate;
        }
    }

    return null;
}

/** Local binding names of one import clause: default, namespace, named (incl. renamed). */
function localNames(clause: string): string[] {
    const names: string[] = [];
    const rest = clause.trim().replace(/^type\s+/, '');

    const namespace = /\*\s+as\s+([A-Za-z_$][\w$]*)/.exec(rest);

    if (namespace) {
        names.push(namespace[1]);
    }

    const named = /\{([\s\S]*?)\}/.exec(rest);

    if (named) {
        for (const part of named[1].split(',')) {
            const match = /^(?:type\s+)?(?:[A-Za-z_$][\w$]*\s+as\s+)?([A-Za-z_$][\w$]*)$/.exec(part.trim());

            if (match) {
                names.push(match[1]);
            }
        }
    }

    const defaultImport = /^([A-Za-z_$][\w$]*)\s*(?=,|$)/.exec(rest);

    if (defaultImport) {
        names.push(defaultImport[1]);
    }

    return names;
}

/** Blank out comments (they may hold commented-out imports) before the import scan. */
function stripComments(code: string): string {
    return code.replace(/\/\/[^\n]*|\/\*[\s\S]*?\*\//g, (comment) => comment.replace(/[^\n]/g, ' '));
}

/**
 * Import map of one module: local JSX tag → component slug. Mirrors the
 * babel plugin's Program visitor — every specifier of a relative import
 * that resolves to a library component registers its local name.
 */
function collectImports(code: string, importerDir: string): Map<string, string> {
    const imports = new Map<string, string>();
    const statement = /import\s+(?:([^'";]*?)\s+from\s+)?['"]([^'"]+)['"]/g;

    let match: RegExpExecArray | null;

    while ((match = statement.exec(stripComments(code))) !== null) {
        const slug = resolveComponentSlug(importerDir, match[2]);

        if (slug === null) {
            continue;
        }

        for (const local of localNames(match[1] ?? '')) {
            imports.set(local, slug);
        }
    }

    return imports;
}

/**
 * Insertion point of one opening element starting after its tag name: the
 * index of the closing `>` — or of the `/` of a self-closing `/>` — so the
 * injected attributes land at the END of the attribute list, exactly where
 * the babel plugin pushes them. -1 when no end is found.
 */
function openingTagEnd(code: string, from: number): number {
    let depth = 0;
    let state: 'code' | 'sq' | 'dq' | 'tpl' = 'code';
    const templateDepths: number[] = [];
    let i = from;

    while (i < code.length) {
        const char = code[i];
        const next = code[i + 1];

        if (state === 'sq' || state === 'dq') {
            const quote = state === 'sq' ? "'" : '"';

            if (char === '\\') {
                i += 2;
            } else {
                if (char === quote) {
                    state = 'code';
                }

                i++;
            }

            continue;
        }

        if (state === 'tpl') {
            if (char === '\\') {
                i += 2;
                continue;
            }

            if (char === '`') {
                state = 'code';
                i++;
                continue;
            }

            if (char === '$' && next === '{') {
                templateDepths.push(1);
                state = 'code';
                i += 2;
                continue;
            }

            i++;
            continue;
        }

        // code state
        if (templateDepths.length > 0) {
            if (char === '{') {
                templateDepths[templateDepths.length - 1]++;
            } else if (char === '}') {
                templateDepths[templateDepths.length - 1]--;

                if (templateDepths[templateDepths.length - 1] === 0) {
                    templateDepths.pop();
                    state = 'tpl';
                }
            }
        } else if (char === "'") {
            state = 'sq';
        } else if (char === '"') {
            state = 'dq';
        } else if (char === '`') {
            state = 'tpl';
        } else if (char === '{') {
            depth++;
        } else if (char === '}') {
            depth--;
        } else if (char === '>' && depth === 0) {
            let insertAt = i;
            let cursor = i - 1;

            while (cursor >= from && /\s/.test(code[cursor])) {
                cursor--;
            }

            if (cursor >= from && code[cursor] === '/') {
                insertAt = cursor;
            }

            return insertAt;
        }

        i++;
    }

    return -1;
}

/**
 * Inject the data-fp-* attributes into one TSX module. `importerDir` is the
 * module's directory inside the virtual FS (`''` for the generated entry),
 * `code` the edited source. Returns the instrumented source.
 */
export function instrumentReactSource(code: string, importerDir: string): string {
    const imports = collectImports(code, importerDir);

    if (imports.size === 0) {
        return code;
    }

    const counters = new Map<string, number>();
    const insertions: Array<{ at: number; text: string }> = [];
    const templateDepths: number[] = [];
    let state: 'code' | 'line' | 'block' | 'sq' | 'dq' | 'tpl' = 'code';
    let i = 0;

    while (i < code.length) {
        const char = code[i];
        const next = code[i + 1];

        if (state === 'line') {
            if (char === '\n') {
                state = 'code';
            }

            i++;
            continue;
        }

        if (state === 'block') {
            if (char === '*' && next === '/') {
                state = 'code';
                i += 2;
            } else {
                i++;
            }

            continue;
        }

        if (state === 'sq' || state === 'dq') {
            const quote = state === 'sq' ? "'" : '"';

            if (char === '\\') {
                i += 2;
            } else {
                if (char === quote) {
                    state = 'code';
                }

                i++;
            }

            continue;
        }

        if (state === 'tpl') {
            if (char === '\\') {
                i += 2;
                continue;
            }

            if (char === '`') {
                state = 'code';
                i++;
                continue;
            }

            if (char === '$' && next === '{') {
                templateDepths.push(1);
                state = 'code';
                i += 2;
                continue;
            }

            i++;
            continue;
        }

        // code state
        if (char === '/' && next === '/') {
            state = 'line';
            i += 2;
            continue;
        }

        if (char === '/' && next === '*') {
            state = 'block';
            i += 2;
            continue;
        }

        if (char === "'") {
            state = 'sq';
            i++;
            continue;
        }

        if (char === '"') {
            state = 'dq';
            i++;
            continue;
        }

        if (char === '`') {
            state = 'tpl';
            i++;
            continue;
        }

        if (templateDepths.length > 0) {
            if (char === '{') {
                templateDepths[templateDepths.length - 1]++;
            } else if (char === '}') {
                templateDepths[templateDepths.length - 1]--;

                if (templateDepths[templateDepths.length - 1] === 0) {
                    templateDepths.pop();
                    state = 'tpl';
                }
            }

            i++;
            continue;
        }

        if (char === '<') {
            if (next === '/') {
                // Closing element: no attributes — skip to its `>`.
                const close = code.indexOf('>', i + 2);

                i = close === -1 ? code.length : close + 1;
                continue;
            }

            if (next !== undefined && /[A-Z]/.test(next)) {
                let cursor = i + 1;

                while (cursor < code.length && /[\w$]/.test(code[cursor])) {
                    cursor++;
                }

                const name = code.slice(i + 1, cursor);

                // Member expressions (`<Icons.X/>`) stay untouched — the
                // babel plugin only visits JSXIdentifier tags.
                if (code[cursor] !== '.') {
                    const slug = imports.get(name);

                    if (slug !== undefined) {
                        const end = openingTagEnd(code, cursor);

                        if (end !== -1) {
                            const instance = (counters.get(slug) ?? 0) + 1;
                            counters.set(slug, instance);

                            insertions.push({ at: end, text: ` data-fp-c="${slug}" data-fp-i="${instance}"` });
                        }
                    }
                }

                i = cursor;
                continue;
            }

            i++;
            continue;
        }

        i++;
    }

    let instrumented = code;

    /* Descending position order — an insertion earlier in the source must
     * never shift the computed position of a later one (nested JSX inside
     * attribute expressions pushes the outer element first). */
    for (const { at, text } of insertions.sort((a, b) => b.at - a.at)) {
        instrumented = instrumented.slice(0, at) + text + instrumented.slice(at);
    }

    return instrumented;
}
