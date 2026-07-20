import { cn } from '@/lib/utils';
import type { ComponentDetailData, Framework } from '@/types/catalog';
import { ArrowLeftRight, ExternalLink } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { ModalHeader } from './modal-header';
import { ModalToolbar, WIDTH_PRESETS } from './modal-toolbar';
import { StructureTree, type HighlightPin } from './structure-tree';
import { CodeTab, DataTab, DocsTab, GatedContent } from './tab-panels';
import { usePreviewLayout } from './use-preview-layout';

const TABS = ['Preview', 'Code', 'Data', 'Docs'] as const;
type Tab = (typeof TABS)[number];

const MIN_STAGE_WIDTH = 320;

export interface PreviewModalProps {
    component: ComponentDetailData;
    initialFramework: Framework;
    /** inline = detail-page main section; overlay = dialog from catalog cards. */
    variant: 'inline' | 'overlay';
    onClose?: () => void;
    className?: string;
}

/**
 * Preview modal (SPEC §5.4/§5.5): fixed header + toolbar, Preview|Code|Data|
 * Docs tabs, structure tree with one-way postMessage highlight sync into the
 * sandboxed preview iframe, swappable/draggable pane layout persisted via
 * usePreviewLayout, keyboard shortcuts (1–4 viewports, r/v framework, esc).
 * The SAME component renders inline on the component page and as an overlay
 * from catalog cards.
 */
export function PreviewModal({ component, initialFramework, variant, onClose, className }: PreviewModalProps) {
    const [framework, setFramework] = useState<Framework>(initialFramework);
    const [tab, setTab] = useState<Tab>('Preview');
    const [width, setWidth] = useState<number>(0); // 0 = full
    const [darkMode, setDarkMode] = useState(false);
    const [structureVisible, setStructureVisible] = useState(true);
    const [pinned, setPinned] = useState<HighlightPin | null>(null);

    const iframeRef = useRef<HTMLIFrameElement | null>(null);
    const frameWrapperRef = useRef<HTMLDivElement | null>(null);
    const panesRef = useRef<HTMLDivElement | null>(null);
    const [measuredWidth, setMeasuredWidth] = useState<number | null>(null);
    const [previewReady, setPreviewReady] = useState(false);
    const [previewHeight, setPreviewHeight] = useState<number | null>(null);

    const { layout, setSide, setSplit } = usePreviewLayout();

    const previewUrl = component.previews[framework] ?? component.previews.react ?? component.previews.vue;
    const interactions = component.features.tree_interactions;

    /* ------------------------------------------------------------------ */
    /* postMessage protocol (SPEC §5.3) — parent side                      */
    /* ------------------------------------------------------------------ */

    const postToPreview = useCallback((message: Record<string, unknown>) => {
        iframeRef.current?.contentWindow?.postMessage(message, '*');
    }, []);

    useEffect(() => {
        const handleMessage = (event: MessageEvent) => {
            if (event.source !== iframeRef.current?.contentWindow) {
                return;
            }

            const data = event.data as { type?: unknown; px?: unknown } | null;

            if (!data || typeof data !== 'object') {
                return;
            }

            if (data.type === 'ready') {
                setPreviewReady(true);
            } else if (data.type === 'height' && typeof data.px === 'number') {
                setPreviewHeight(Math.min(Math.max(Math.ceil(data.px), 160), 5000));
            }
        };

        window.addEventListener('message', handleMessage);

        return () => window.removeEventListener('message', handleMessage);
    }, []);

    // A framework (or artifact) switch reloads the iframe — wait for ready again.
    useEffect(() => {
        setPreviewReady(false);
        setPreviewHeight(null);
        setPinned(null);
    }, [framework, previewUrl]);

    // Dark/light is pushed whenever it changes or a fresh preview signals ready.
    useEffect(() => {
        if (previewReady) {
            postToPreview({ type: 'theme', mode: darkMode ? 'dark' : 'light' });
        }
    }, [darkMode, previewReady, postToPreview]);

    const highlight = useCallback(
        (slug: string, instance: number | null) => {
            postToPreview({ type: 'highlight', slug, instance });
        },
        [postToPreview],
    );

    // Leaving a row restores the pinned highlight (or clears when unpinned).
    const clearHighlight = useCallback(() => {
        if (pinned) {
            postToPreview({ type: 'highlight', slug: pinned.slug, instance: pinned.instance });
        } else {
            postToPreview({ type: 'clear' });
        }
    }, [pinned, postToPreview]);

    const pin = useCallback(
        (next: HighlightPin | null) => {
            setPinned(next);

            if (next) {
                postToPreview({ type: 'highlight', slug: next.slug, instance: next.instance });
            } else {
                postToPreview({ type: 'clear' });
            }
        },
        [postToPreview],
    );

    /* ------------------------------------------------------------------ */
    /* Live width readout (ResizeObserver on the iframe wrapper)           */
    /* ------------------------------------------------------------------ */

    useEffect(() => {
        const element = frameWrapperRef.current;

        if (!element || typeof ResizeObserver === 'undefined') {
            return;
        }

        const observer = new ResizeObserver((entries) => {
            for (const entry of entries) {
                setMeasuredWidth(Math.round(entry.contentRect.width));
            }
        });

        observer.observe(element);

        return () => observer.disconnect();
    }, [tab, structureVisible, previewUrl]);

    /* ------------------------------------------------------------------ */
    /* Keyboard shortcuts: 1–4 viewports, r/v framework, esc close         */
    /* ------------------------------------------------------------------ */

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.metaKey || event.ctrlKey || event.altKey) {
                return;
            }

            const target = event.target as HTMLElement | null;

            if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) {
                return;
            }

            const preset = WIDTH_PRESETS.find((candidate) => candidate.hotkey === event.key);

            if (preset) {
                setWidth(preset.value);
                return;
            }

            if (event.key === 'r') {
                setFramework('react');
            } else if (event.key === 'v') {
                setFramework('vue');
            } else if (event.key === 'Escape' && variant === 'overlay') {
                onClose?.();
            }
        };

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [variant, onClose]);

    /* ------------------------------------------------------------------ */
    /* Drag handles: stage width + pane split                              */
    /* ------------------------------------------------------------------ */

    const trackPointer = (onMove: (event: PointerEvent) => void) => {
        const handleUp = () => {
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', handleUp);
        };

        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', handleUp);
    };

    const startStageDrag = (event: React.PointerEvent<HTMLButtonElement>) => {
        event.preventDefault();

        const frame = frameWrapperRef.current;

        if (!frame) {
            return;
        }

        const startX = event.clientX;
        const startWidth = frame.getBoundingClientRect().width;

        trackPointer((move) => {
            setWidth(Math.max(MIN_STAGE_WIDTH, Math.round(startWidth + move.clientX - startX)));
        });
    };

    const startSplitDrag = (event: React.PointerEvent<HTMLDivElement>) => {
        event.preventDefault();

        const panes = panesRef.current;

        if (!panes) {
            return;
        }

        const rect = panes.getBoundingClientRect();
        const side = layout.side;

        trackPointer((move) => {
            const percent = ((move.clientX - rect.left) / rect.width) * 100;
            setSplit(side === 'left' ? percent : 100 - percent);
        });
    };

    /* ------------------------------------------------------------------ */

    const treePane = structureVisible && (
        <aside
            style={{ width: `${layout.split}%` }}
            className="w-full shrink-0 overflow-y-auto rounded-xl border border-neutral-200 bg-neutral-50/60 p-3 max-lg:w-full!"
        >
            <div className="flex items-center justify-between gap-2 px-1">
                <h3 className="text-xs font-semibold tracking-wide text-neutral-400 uppercase">Structure</h3>
                <button
                    type="button"
                    onClick={() => setSide(layout.side === 'left' ? 'right' : 'left')}
                    title="Swap panes"
                    className="rounded p-1 text-neutral-400 transition hover:bg-neutral-200 hover:text-neutral-700"
                >
                    <ArrowLeftRight className="h-3.5 w-3.5" />
                    <span className="sr-only">Swap panes</span>
                </button>
            </div>
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
        </aside>
    );

    const splitHandle = structureVisible && (
        <div
            role="separator"
            aria-orientation="vertical"
            aria-label="Resize structure pane"
            onPointerDown={startSplitDrag}
            className="hidden w-1.5 shrink-0 cursor-col-resize rounded-full bg-neutral-200 transition hover:bg-neutral-400 lg:block"
        />
    );

    const stagePane = (
        <div className="min-w-0 flex-1 overflow-x-auto rounded-xl border border-neutral-200 bg-neutral-100 p-4">
            {previewUrl ? (
                <div className="relative mx-auto" ref={frameWrapperRef} style={{ width: width === 0 ? '100%' : `${width}px` }}>
                    <iframe
                        key={framework}
                        ref={iframeRef}
                        src={previewUrl}
                        title={`${component.name} ${framework} preview`}
                        sandbox="allow-scripts"
                        className="block w-full rounded-lg border border-neutral-200 bg-white"
                        style={{ height: previewHeight !== null ? `${previewHeight}px` : '480px' }}
                    />
                    <button
                        type="button"
                        onPointerDown={startStageDrag}
                        aria-label="Drag to resize preview width"
                        className="absolute top-0 -right-3 hidden h-full w-3 cursor-ew-resize touch-none md:block"
                    >
                        <span className="mx-auto block h-full w-1 rounded-full bg-neutral-300 transition hover:bg-neutral-500" />
                    </button>
                </div>
            ) : (
                <div className="flex h-[320px] items-center justify-center rounded-lg border border-dashed border-neutral-300 bg-white">
                    <p className="text-sm text-neutral-400">Preview is being built for this component.</p>
                </div>
            )}
        </div>
    );

    return (
        <section aria-label={`${component.name} preview`} className={cn('flex flex-col', variant === 'overlay' && 'h-full min-h-0', className)}>
            <ModalHeader component={component} framework={framework} variant={variant} onClose={onClose} />

            <ModalToolbar
                width={width}
                measuredWidth={measuredWidth}
                framework={framework}
                darkMode={darkMode}
                darkToggleEnabled={component.features.dark_toggle}
                structureVisible={structureVisible}
                onWidthChange={setWidth}
                onFrameworkChange={setFramework}
                onToggleDark={() => setDarkMode((current) => !current)}
                onToggleStructure={() => setStructureVisible((current) => !current)}
            />

            <div className="border-b border-neutral-200 px-6">
                <div className="flex gap-1" role="tablist" aria-label="Component detail tabs">
                    {TABS.map((candidate) => (
                        <button
                            key={candidate}
                            type="button"
                            role="tab"
                            aria-selected={tab === candidate}
                            onClick={() => setTab(candidate)}
                            className={cn(
                                '-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition',
                                tab === candidate
                                    ? 'border-neutral-900 text-neutral-900'
                                    : 'border-transparent text-neutral-400 hover:text-neutral-700',
                            )}
                        >
                            {candidate}
                        </button>
                    ))}
                </div>
            </div>

            <div className={cn('px-6 py-6', variant === 'overlay' && 'min-h-0 flex-1 overflow-y-auto')}>
                {tab === 'Preview' && (
                    <div className="space-y-3">
                        {previewUrl && (
                            <div className="flex justify-end">
                                <a
                                    href={previewUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 text-xs font-medium text-neutral-500 transition hover:text-neutral-900"
                                >
                                    Open preview in new tab
                                    <ExternalLink className="h-3 w-3" />
                                </a>
                            </div>
                        )}
                        <div ref={panesRef} className="flex flex-col gap-3 lg:flex-row lg:items-stretch">
                            {layout.side === 'left' ? (
                                <>
                                    {treePane}
                                    {splitHandle}
                                    {stagePane}
                                </>
                            ) : (
                                <>
                                    {stagePane}
                                    {splitHandle}
                                    {treePane}
                                </>
                            )}
                        </div>
                    </div>
                )}

                {tab === 'Code' && (
                    <GatedContent entitled={component.entitled}>
                        <CodeTab component={component} framework={framework} />
                    </GatedContent>
                )}

                {tab === 'Data' && (
                    <GatedContent entitled={component.entitled}>
                        <DataTab component={component} />
                    </GatedContent>
                )}

                {tab === 'Docs' && <DocsTab component={component} />}
            </div>
        </section>
    );
}
