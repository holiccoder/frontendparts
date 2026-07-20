import { AccessBadge, LevelBadge } from '@/components/catalog/badges';
import type { ComponentDetailData } from '@/types/catalog';
import { Link } from '@inertiajs/react';
import { Check, Download, ExternalLink, Plus, Share2, X } from 'lucide-react';
import { useState } from 'react';

interface ModalHeaderProps {
    component: ComponentDetailData;
    variant: 'inline' | 'overlay';
    onClose?: () => void;
}

/**
 * Fixed modal header (SPEC §5.4): name, level + access badges, usage and
 * industry tags, citation, and the action row. Add to Project / Download are
 * Phase 2 surfaces and render disabled with tooltips; Share copies the
 * canonical URL.
 */
export function ModalHeader({ component, variant, onClose }: ModalHeaderProps) {
    const [copied, setCopied] = useState(false);

    const share = async () => {
        const url = `${window.location.origin}/components/${component.usage.slug}/${component.basename}`;

        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard API unavailable (permissions) — no-op; the canonical
            // URL is always reachable from the address bar / citation block.
        }
    };

    return (
        <div className="flex flex-wrap items-start justify-between gap-4 border-b border-neutral-200 px-6 py-4">
            <div className="max-w-2xl min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <h2 className="truncate text-lg font-semibold tracking-tight text-neutral-900">{component.name}</h2>
                    <LevelBadge level={component.level} />
                    <AccessBadge access={component.access} />
                    <span className="text-xs font-medium text-neutral-400">v{component.version}</span>
                </div>

                <div className="mt-2 flex flex-wrap items-center gap-1.5">
                    <Link
                        href={`/components/${component.usage.slug}`}
                        className="inline-flex items-center rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-600 transition hover:bg-neutral-200"
                    >
                        {component.usage.name}
                    </Link>
                    {component.industries.map((industry) => (
                        <Link
                            key={industry.slug}
                            href={`/industries/${industry.slug}`}
                            className="inline-flex items-center rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-600 transition hover:bg-neutral-200"
                        >
                            {industry.name}
                        </Link>
                    ))}
                    {component.tags.map((tag) => (
                        <span
                            key={tag.slug}
                            className="inline-flex items-center rounded-full border border-neutral-200 px-2 py-0.5 text-[11px] font-medium text-neutral-500"
                        >
                            #{tag.name}
                        </span>
                    ))}
                </div>

                {component.citation.source_name && component.citation.source_url && (
                    <p className="mt-2 text-xs text-neutral-500">
                        Layout reference:{' '}
                        <a
                            href={component.citation.source_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1 font-medium text-neutral-900 underline underline-offset-4"
                        >
                            {component.citation.source_name}
                            <ExternalLink className="h-3 w-3" />
                        </a>
                    </p>
                )}
            </div>

            <div className="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    disabled
                    title="Coming in Phase 2"
                    className="inline-flex cursor-not-allowed items-center gap-1.5 rounded-md bg-neutral-900 px-3.5 py-2 text-sm font-medium text-white opacity-50"
                >
                    <Plus className="h-4 w-4" />
                    Add to Project
                </button>
                <button
                    type="button"
                    disabled
                    title="Coming in Phase 2"
                    className="inline-flex cursor-not-allowed items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 opacity-50"
                >
                    <Download className="h-4 w-4" />
                    Download
                </button>
                <button
                    type="button"
                    onClick={share}
                    className="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 transition hover:border-neutral-400"
                >
                    {copied ? <Check className="h-4 w-4 text-emerald-600" /> : <Share2 className="h-4 w-4" />}
                    {copied ? 'Copied!' : 'Share'}
                </button>
                {variant === 'overlay' && onClose && (
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Close preview"
                        className="inline-flex items-center justify-center rounded-md border border-neutral-300 bg-white p-2 text-neutral-500 transition hover:border-neutral-400 hover:text-neutral-900"
                    >
                        <X className="h-4 w-4" />
                    </button>
                )}
            </div>
        </div>
    );
}
