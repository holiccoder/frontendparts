import { xsrfToken } from '@/lib/xsrf';
import type { ComponentDetailData } from '@/types/catalog';
import { AlertTriangle, Download, Loader2, MonitorSmartphone, RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { VueLiveEditSession } from './live-edit-runtime-vue';
import { useIsDesktop } from './use-is-desktop';

/**
 * Edit tab — Vue (SPEC §5.6; Phase 3.2): the same live-edit surface as the
 * React side, driven by @vue/repl. The heavy runtime (vue + @vue/repl +
 * CodeMirror) is lazy-loaded on first desktop mount and never lands in the
 * main bundle. The Repl provides multi-file SFC editing, the `src/data.ts`
 * data editor and instant in-browser re-render; this wrapper keeps the
 * React-side chrome identical to the React edit tab: desktop gate, Reset +
 * Download edits actions, and the error banner. Download posts the Repl's
 * current sources to the shared edit-download endpoint (framework=vue) —
 * instant, no server build.
 */
export default function VueEditTab({ component, darkMode }: { component: ComponentDetailData; darkMode: boolean }) {
    const payload = component.edit?.vue ?? null;
    const isDesktop = useIsDesktop();

    const [sessionReady, setSessionReady] = useState(false);
    const [sessionFailed, setSessionFailed] = useState<string | null>(null);
    const [downloadFailed, setDownloadFailed] = useState(false);
    const [downloading, setDownloading] = useState(false);
    /* Reset = pristine session recreation (the Repl owns its file state). */
    const [resetKey, setResetKey] = useState(0);

    const containerRef = useRef<HTMLDivElement | null>(null);
    const sessionRef = useRef<VueLiveEditSession | null>(null);
    const darkRef = useRef(darkMode);
    darkRef.current = darkMode;

    /* ------------------------------------------------------------------ */
    /* Lazy runtime: create the session on first desktop mount             */
    /* ------------------------------------------------------------------ */

    useEffect(() => {
        if (!isDesktop || payload === null) {
            return;
        }

        let cancelled = false;
        let session: VueLiveEditSession | null = null;

        import('./live-edit-runtime-vue')
            .then(async (runtime) => {
                if (cancelled || containerRef.current === null) {
                    return;
                }

                session = await runtime.createVueLiveEditSession({
                    payload,
                    container: containerRef.current,
                    dark: darkRef.current,
                });

                if (cancelled) {
                    session.dispose();
                    return;
                }

                sessionRef.current = session;
                setSessionReady(true);
            })
            .catch((error: unknown) => {
                if (!cancelled) {
                    setSessionFailed(error instanceof Error ? error.message : String(error));
                }
            });

        return () => {
            cancelled = true;
            session?.dispose();
            sessionRef.current = null;
            setSessionReady(false);
        };
    }, [isDesktop, payload, resetKey]);

    /* Theme pushes into the Repl (chrome + preview iframe). */
    useEffect(() => {
        if (sessionReady) {
            sessionRef.current?.setDark(darkMode);
        }
    }, [darkMode, sessionReady]);

    /* ------------------------------------------------------------------ */
    /* Actions                                                             */
    /* ------------------------------------------------------------------ */

    const reset = () => {
        setDownloadFailed(false);
        setResetKey((key) => key + 1);
    };

    /* Instant download of edits (SPEC §5.6 — sources, no server build). */
    const download = async () => {
        const files = sessionRef.current?.files() ?? [];

        if (files.length === 0) {
            return;
        }

        setDownloading(true);
        setDownloadFailed(false);

        try {
            const response = await fetch(`/components/${component.usage.slug}/${component.basename}/edit-download`, {
                method: 'POST',
                headers: {
                    'X-XSRF-TOKEN': xsrfToken(),
                    'Content-Type': 'application/json',
                    Accept: 'application/zip',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ framework: 'vue', files }),
            });

            if (!response.ok) {
                throw new Error(`Download failed with ${response.status}`);
            }

            const url = URL.createObjectURL(await response.blob());
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = `${component.basename}-vue-edited.zip`;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            URL.revokeObjectURL(url);
        } catch {
            setDownloadFailed(true);
        } finally {
            setDownloading(false);
        }
    };

    /* ------------------------------------------------------------------ */

    if (payload === null) {
        return null;
    }

    if (!isDesktop) {
        return (
            <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-neutral-300 bg-neutral-50 px-6 py-16 text-center">
                <MonitorSmartphone className="h-6 w-6 text-neutral-400" />
                <p className="text-sm font-semibold text-neutral-900">Live edit is best on desktop</p>
                <p className="max-w-sm text-xs leading-5 text-neutral-500">
                    The in-browser editor compiles the component right here on your machine and needs a desktop-sized viewport. Open this component on
                    a laptop or desktop to tweak code and sample data with instant re-render.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-xs text-neutral-500">
                    Editing the Vue sources — <span className="font-mono">data.ts</span> holds the sample data; changes re-render instantly.
                </p>

                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={reset}
                        className="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 transition hover:border-neutral-400"
                    >
                        <RotateCcw className="h-3.5 w-3.5" />
                        Reset
                    </button>
                    <button
                        type="button"
                        onClick={download}
                        disabled={downloading || !sessionReady}
                        className="inline-flex items-center gap-1.5 rounded-md bg-neutral-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-neutral-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {downloading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Download className="h-3.5 w-3.5" />}
                        Download edits
                    </button>
                </div>
            </div>

            {(sessionFailed !== null || downloadFailed) && (
                <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900" role="alert">
                    <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                    <div className="space-y-1 font-mono">
                        {sessionFailed !== null && <p>Live edit runtime failed to load: {sessionFailed}</p>}
                        {downloadFailed && <p>Download failed — please try again.</p>}
                    </div>
                </div>
            )}

            {/* The Repl renders its own compile/runtime error overlay inside
             * the preview pane, so no separate build-error banner is needed. */}
            <div className="relative">
                <div ref={containerRef} className="h-[640px] overflow-hidden rounded-xl border border-neutral-200 bg-white" />
                {!sessionReady && sessionFailed === null && (
                    <div className="absolute inset-0 flex items-center justify-center gap-2 rounded-xl bg-white/90 text-xs font-medium text-neutral-500">
                        <Loader2 className="h-4 w-4 animate-spin" />
                        Loading the live edit runtime…
                    </div>
                )}
            </div>
        </div>
    );
}
