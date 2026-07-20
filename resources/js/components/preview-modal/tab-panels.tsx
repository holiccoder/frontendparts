import type { ComponentDetailData, ComponentFile, Framework } from '@/types/catalog';
import { Check, Copy, Lock } from 'lucide-react';
import { useState, type PropsWithChildren } from 'react';

function CopyButton({ text, label }: { text: string; label: string }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard unavailable — nothing sensible to fall back to here.
        }
    };

    return (
        <button
            type="button"
            onClick={copy}
            title={label}
            className="inline-flex items-center gap-1 rounded border border-neutral-600 px-1.5 py-0.5 text-[10px] font-medium text-neutral-300 transition hover:border-neutral-400 hover:text-white"
        >
            {copied ? <Check className="h-3 w-3 text-emerald-400" /> : <Copy className="h-3 w-3" />}
            {copied ? 'Copied' : 'Copy'}
        </button>
    );
}

/**
 * Pro blur-gate placeholder (SPEC §5.4): until Phase 2 billing exists,
 * `entitled` comes from the payload (free components, or any authenticated
 * user). Locked visitors see Code/Data content blurred behind an Upgrade CTA.
 */
export function GatedContent({ entitled, children }: PropsWithChildren<{ entitled: boolean }>) {
    if (entitled) {
        return <>{children}</>;
    }

    return (
        <div className="relative overflow-hidden rounded-xl">
            <div className="pointer-events-none blur-sm select-none" aria-hidden="true">
                {children}
            </div>
            <div className="absolute inset-0 flex items-center justify-center">
                <div className="rounded-xl border border-neutral-200 bg-white/95 px-8 py-6 text-center shadow-lg">
                    <Lock className="mx-auto h-5 w-5 text-neutral-400" />
                    <p className="mt-2 text-sm font-semibold text-neutral-900">Pro component</p>
                    <p className="mt-1 text-xs text-neutral-500">Source code and sample data are included with Pro.</p>
                    <a
                        href="#"
                        className="mt-4 inline-flex rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-700"
                    >
                        Upgrade
                    </a>
                </div>
            </div>
        </div>
    );
}

function FilePanel({ file }: { file: ComponentFile }) {
    return (
        <div>
            <div className="flex items-center justify-between gap-3 rounded-t-lg border border-b-0 border-neutral-200 bg-neutral-100 px-4 py-2">
                <span className="truncate font-mono text-xs text-neutral-600">{file.path}</span>
                <CopyButton text={file.code} label={`Copy ${file.path}`} />
            </div>
            <pre className="max-h-[560px] overflow-auto rounded-b-lg border border-neutral-200 bg-neutral-950 p-4 text-xs leading-5 text-neutral-100">
                <code>{file.code}</code>
            </pre>
        </div>
    );
}

/** Code tab: one file panel per closure file of the selected framework. */
export function CodeTab({ component, framework }: { component: ComponentDetailData; framework: Framework }) {
    const files = component.files[framework];
    const fallback = component.files.react.length > 0 ? component.files.react : component.files.vue;
    const shown = files.length > 0 ? files : fallback;

    if (shown.length === 0) {
        return <p className="py-10 text-center text-sm text-neutral-400">Source files are not available in this environment.</p>;
    }

    return (
        <div className="space-y-6">
            {shown.map((file) => (
                <FilePanel key={file.path} file={file} />
            ))}
        </div>
    );
}

/** Data tab: pretty-printed sample JSON + copy. */
export function DataTab({ component }: { component: ComponentDetailData }) {
    if (Object.keys(component.data).length === 0) {
        return <p className="py-10 text-center text-sm text-neutral-400">No sample data for this component.</p>;
    }

    const pretty = JSON.stringify(component.data, null, 2);

    return (
        <div>
            <div className="flex items-center justify-between gap-3 rounded-t-lg border border-b-0 border-neutral-200 bg-neutral-100 px-4 py-2">
                <span className="font-mono text-xs text-neutral-600">data.json</span>
                <CopyButton text={pretty} label="Copy sample data" />
            </div>
            <pre className="max-h-[560px] overflow-auto rounded-b-lg border border-neutral-200 bg-neutral-950 p-4 text-xs leading-5 text-neutral-100">
                <code>{pretty}</code>
            </pre>
        </div>
    );
}

/** Docs tab: usage scenario, resolved deps, props table, version/changelog. */
export function DocsTab({ component }: { component: ComponentDetailData }) {
    const params = Object.entries(component.params);

    return (
        <div className="space-y-10">
            <section>
                <h3 className="text-sm font-semibold tracking-wide text-neutral-400 uppercase">Usage</h3>
                <p className="mt-3 text-sm leading-6 text-neutral-600">
                    {component.name} is a {component.level}-level {component.usage.name.toLowerCase()} component for production pages. Drop it into a
                    React or Vue app, pass the props below (the sample data from the Data tab works out of the box), and adapt the copy to your brand.
                </p>
            </section>

            <section>
                <h3 className="text-sm font-semibold tracking-wide text-neutral-400 uppercase">Props</h3>
                {params.length > 0 ? (
                    <div className="mt-3 overflow-x-auto rounded-lg border border-neutral-200">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-neutral-200 bg-neutral-50 text-xs tracking-wide text-neutral-500 uppercase">
                                    <th className="px-4 py-2.5 font-semibold">Prop</th>
                                    <th className="px-4 py-2.5 font-semibold">Type</th>
                                    <th className="px-4 py-2.5 font-semibold">Default</th>
                                    <th className="px-4 py-2.5 font-semibold">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                {params.map(([name, definition]) => (
                                    <tr key={name} className="border-b border-neutral-100 last:border-0">
                                        <td className="px-4 py-2.5 font-mono text-xs font-semibold">{name}</td>
                                        <td className="px-4 py-2.5 font-mono text-xs text-neutral-600">
                                            {definition.type}
                                            {definition.type === 'enum' && definition.options ? ` (${definition.options.join(' | ')})` : ''}
                                        </td>
                                        <td className="px-4 py-2.5 font-mono text-xs text-neutral-600">
                                            {JSON.stringify(definition.default ?? null)}
                                        </td>
                                        <td className="px-4 py-2.5 text-neutral-600">{definition.description}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <p className="mt-3 text-sm text-neutral-400">This component takes no params.</p>
                )}
            </section>

            <section>
                <h3 className="text-sm font-semibold tracking-wide text-neutral-400 uppercase">Dependencies</h3>
                {component.deps.length > 0 ? (
                    <ul className="mt-3 flex flex-wrap gap-2">
                        {component.deps.map((dep) => (
                            <li
                                key={dep}
                                className="rounded-full border border-neutral-200 bg-neutral-50 px-3 py-1 font-mono text-xs text-neutral-700"
                            >
                                {dep}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="mt-3 inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20 ring-inset">
                        Zero npm dependencies
                    </p>
                )}
            </section>

            <section>
                <h3 className="text-sm font-semibold tracking-wide text-neutral-400 uppercase">Version &amp; changelog</h3>
                <p className="mt-3 font-mono text-sm text-neutral-700">v{component.version}</p>
                <ul className="mt-2 space-y-1 text-sm text-neutral-600">
                    <li className="flex gap-2">
                        <span className="font-mono text-xs text-neutral-400">v{component.version}</span>
                        <span>— initial release</span>
                    </li>
                </ul>
            </section>
        </div>
    );
}
