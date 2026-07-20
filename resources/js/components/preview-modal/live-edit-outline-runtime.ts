/**
 * Structure-tree outline runtime for live-edit iframes (SPEC §5.3, §5.6;
 * Phase 3.3).
 *
 * A verbatim port of the postMessage paint/restore logic the server-side
 * PreviewBuilder inlines into every prebuilt preview artifact, so the edit
 * mode iframes answer the exact same protocol: parent → iframe `highlight`
 * `{type:'highlight', slug, instance:n|null}` soft-outlines every
 * `[data-fp-c="<slug>"]` element (instance null) or strong-outlines the nth
 * match (`data-fp-i`) and scrolls it into view; `clear` removes all
 * outlines. Written without template literals so it inlines safely into a
 * generated <script> (React edit frame document, Vue Repl headHTML).
 */
export const OUTLINE_RUNTIME_SCRIPT = [
    'var FP_OUTLINE_ACCENT = "#6366f1";',
    'var fpOutlineTouched = [];',
    'function fpOutlineRestoreAll() {',
    '    fpOutlineTouched.forEach(function (entry) {',
    '        entry.el.style.outline = entry.outline;',
    '        entry.el.style.outlineOffset = entry.outlineOffset;',
    '        entry.el.style.boxShadow = entry.boxShadow;',
    '        entry.el.style.background = entry.background;',
    '    });',
    '    fpOutlineTouched = [];',
    '}',
    'function fpOutlinePaint(el, strong) {',
    '    fpOutlineTouched.push({',
    '        el: el,',
    '        outline: el.style.outline,',
    '        outlineOffset: el.style.outlineOffset,',
    '        boxShadow: el.style.boxShadow,',
    '        background: el.style.background',
    '    });',
    '    el.style.outline = "2px " + (strong ? "solid " : "dashed ") + FP_OUTLINE_ACCENT;',
    '    el.style.outlineOffset = "2px";',
    '    if (strong) {',
    '        el.style.boxShadow = "0 0 0 4px rgba(99, 102, 241, 0.25)";',
    '    } else {',
    '        el.style.background = "rgba(99, 102, 241, 0.06)";',
    '    }',
    '}',
    'function fpOutlineHighlight(slug, instance) {',
    '    fpOutlineRestoreAll();',
    '    if (typeof slug !== "string" || slug === "") {',
    '        return;',
    '    }',
    "    var matches = document.querySelectorAll('[data-fp-c=\"' + slug + '\"]');",
    '    if (instance === null || instance === undefined) {',
    '        matches.forEach(function (el) { fpOutlinePaint(el, false); });',
    '        return;',
    '    }',
    '    matches.forEach(function (el) {',
    '        if (el.getAttribute("data-fp-i") === String(instance)) {',
    '            fpOutlinePaint(el, true);',
    '            el.scrollIntoView({ block: "nearest", behavior: "smooth" });',
    '        }',
    '    });',
    '}',
    'window.addEventListener("message", function (event) {',
    '    var data = event.data;',
    '    if (!data || typeof data !== "object") {',
    '        return;',
    '    }',
    '    if (data.type === "highlight") {',
    '        fpOutlineHighlight(data.slug, data.instance);',
    '    } else if (data.type === "clear") {',
    '        fpOutlineRestoreAll();',
    '    }',
    '});',
].join('\n');
