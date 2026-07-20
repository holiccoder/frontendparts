import { cn } from '@/lib/utils';
import type { ComponentDetailData, LiveEditOutlines } from '@/types/catalog';
import { useCallback, useState } from 'react';
import { StructureTree, type HighlightPin } from './structure-tree';

/** Sink the pane posts outline messages into (a live-edit session). */
export interface OutlineSink {
    highlight(slug: string, instance: number | null): void;
    clearHighlight(): void;
}

interface EditOutlinesPaneProps {
    component: ComponentDetailData;
    /** Outlines capability declared by the framework's edit payload (Phase 3.3). */
    outlines: LiveEditOutlines;
    /** Live session to post into; null until the runtime is ready. */
    sink: OutlineSink | null;
    className?: string;
}

/**
 * Structure tree in live-edit mode (SPEC §5.6, §5.5; Phase 3.3): the same
 * composition tree as the Preview tab with the same one-way highlight sync —
 * hover/pin posts into the live-edit iframe, whose client-side-injected
 * data-fp-* attributes map nodes to rendered regions. When the payload
 * declares outlines 'unavailable', the SPEC fallback renders instead of
 * silently breaking: the tree stays, hover outlines are off and a note
 * points at the prebuilt Preview tab.
 */
export function EditOutlinesPane({ component, outlines, sink, className }: EditOutlinesPaneProps) {
    const [pinned, setPinned] = useState<HighlightPin | null>(null);

    const available = outlines === 'client-injected';
    const interactions = available && sink !== null && component.features.tree_interactions;

    const highlight = useCallback(
        (slug: string, instance: number | null) => {
            sink?.highlight(slug, instance);
        },
        [sink],
    );

    // Leaving a row restores the pinned highlight (or clears when unpinned).
    const clearHighlight = useCallback(() => {
        if (pinned) {
            sink?.highlight(pinned.slug, pinned.instance);
        } else {
            sink?.clearHighlight();
        }
    }, [pinned, sink]);

    const pin = useCallback(
        (next: HighlightPin | null) => {
            setPinned(next);

            if (next) {
                sink?.highlight(next.slug, next.instance);
            } else {
                sink?.clearHighlight();
            }
        },
        [sink],
    );

    return (
        <section aria-label="Structure tree" className={cn('rounded-xl border border-neutral-200 bg-neutral-50/60 p-3', className)}>
            <div className="flex items-center justify-between gap-2 px-1">
                <h3 className="text-xs font-semibold tracking-wide text-neutral-400 uppercase">Structure</h3>
                {available && <span className="text-[10px] text-neutral-400">hover to outline</span>}
            </div>

            {!available && (
                <p className="mx-1 mt-2 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-xs leading-5 text-neutral-500">
                    Outlines are unavailable in edit mode for this component — the composition tree still shows here; hover outlines work in the
                    prebuilt Preview tab.
                </p>
            )}

            <div className="mt-2">
                <StructureTree
                    tree={component.tree}
                    interactions={interactions}
                    pinned={pinned}
                    onHighlight={highlight}
                    onClearHighlight={clearHighlight}
                    onPin={pin}
                />
            </div>
        </section>
    );
}
