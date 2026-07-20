import { xsrfToken } from '@/lib/xsrf';
import type { ComponentDetailData, Framework } from '@/types/catalog';
import { AlertTriangle, Download, Loader2, MonitorSmartphone, RotateCcw } from 'lucide-react';
import { lazy, Suspense, useCallback, useEffect, useRef, useState } from 'react';
import { formatBuildError, type LiveEditSession } from './live-edit-runtime';
import { useIsDesktop } from './use-is-desktop';

/* Live edit — Vue (SPEC §5.6; Phase 3.2): the @vue/repl surface is a lazy
 * chunk of its own (vue + @vue/repl + CodeMirror), loaded only when the
 * framework toggle is on Vue and the Edit tab opens. */
const VueEditTab = lazy(() => import('./vue-edit-tab'));

/** Debounce between the last keystroke and an in-browser rebuild. */
const REBUILD_DEBOUNCE_MS = 350;

/** Synthetic file-tab id for the JSON sample-data editor. */
const DATA_TAB = 'data.json';

/**
 * Edit tab dispatcher (SPEC §5.6): renders the live-edit surface for the
 * modal's current framework. Vue gets the @vue/repl surface (Phase 3.2);
 * React gets the esbuild-wasm surface (Phase 3.1). A component whose Vue
 * sources are not shipped falls back to the React twin with a note.
 */
export default function EditTab({ component, framework, darkMode }: { component: ComponentDetailData; framework: Framework; darkMode: boolean }) {
    if (framework === 'vue' && component.edit?.vue?.entryFile) {
        return (
            <Suspense
                fallback={
                    <div className="space-y-4" aria-busy="true">
                        <div className="h-8 w-2/3 animate-pulse rounded-md bg-neutral-100" />
                        <div className="h-[560px] animate-pulse rounded-xl bg-neutral-100" />
                    </div>
                }
            >
                <VueEditTab component={component} darkMode={darkMode} />
            </Suspense>
        );
    }

    return (
        <>
            {framework === 'vue' && (
                <p className="mb-3 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-2 text-xs text-neutral-500">
                    This component ships no Vue sources — you’re editing the React twin.
                </p>
            )}
            <ReactEditTab component={component} darkMode={darkMode} />
        </>
    );
}

/**
 * React edit surface (SPEC §5.6; Phase 3.1): multi-file editor over the full
 * composition closure + JSON sample-data editor with instant in-browser
 * re-render (esbuild-wasm; keystrokes never touch the server) + instant
 * download of the edited sources. Lazy-loaded by the modal via React.lazy
 * on first Edit click; desktop-gated with a "best on desktop" notice below.
 */
function ReactEditTab({ component, darkMode }: { component: ComponentDetailData; darkMode: boolean }) {
    const payload = component.edit?.react ?? null;
    const isDesktop = useIsDesktop();

    const [files, setFiles] = useState<Array<{ path: string; code: string }>>(() => payload?.files ?? []);
    const [dataText, setDataText] = useState(() => JSON.stringify(payload?.data[payload.entry] ?? {}, null, 2));
    const [activePath, setActivePath] = useState<string>(() => payload?.files[payload.files.length - 1]?.path ?? DATA_TAB);

    const [dataError, setDataError] = useState<string | null>(null);
    const [buildError, setBuildError] = useState<string | null>(null);
    const [sessionReady, setSessionReady] = useState(false);
    const [sessionFailed, setSessionFailed] = useState<string | null>(null);
    const [previewHeight, setPreviewHeight] = useState<number | null>(null);
    const [downloadFailed, setDownloadFailed] = useState(false);
    const [downloading, setDownloading] = useState(false);

    const iframeRef = useRef<HTMLIFrameElement | null>(null);
    const sessionRef = useRef<LiveEditSession | null>(null);
    const hasBuiltRef = useRef(false);
    const darkRef = useRef(darkMode);
    darkRef.current = darkMode;

    /* Pristine copies power Reset. */
    const pristineRef = useRef({ files, dataText });

    /* ------------------------------------------------------------------ */
    /* Lazy runtime: create the session on first desktop mount             */
    /* ------------------------------------------------------------------ */

    useEffect(() => {
        if (!isDesktop || payload === null) {
            return;
        }

        let cancelled = false;

        import('./live-edit-runtime')
            .then(async (runtime) => {
                if (cancelled || iframeRef.current === null) {
                    return;
                }

                const session = await runtime.createLiveEditSession({
                    payload,
                    iframe: iframeRef.current,
                    dark: darkRef.current,
                    onHeight: setPreviewHeight,
                    onRuntimeError: setBuildError,
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
                    setSessionFailed(formatBuildError(error));
                }
            });

        return () => {
            cancelled = true;
            sessionRef.current?.dispose();
            sessionRef.current = null;
            hasBuiltRef.current = false;
            setSessionReady(false);
        };
    }, [isDesktop, payload]);

    /* ------------------------------------------------------------------ */
    /* Rebuild: immediate for the first render, debounced for edits        */
    /* ------------------------------------------------------------------ */

    const rebuild = useCallback(() => {
        const session = sessionRef.current;

        if (session === null) {
            return;
        }

        session
            .update({ files: new Map(files.map((file) => [file.path, file.code])), dataText })
            .then(() => setBuildError(null))
            .catch((error: unknown) => setBuildError(formatBuildError(error)));
    }, [files, dataText]);

    useEffect(() => {
        if (!sessionReady || dataError !== null) {
            return;
        }

        if (!hasBuiltRef.current) {
            hasBuiltRef.current = true;
            rebuild();
            return;
        }

        const timer = setTimeout(rebuild, REBUILD_DEBOUNCE_MS);

        return () => clearTimeout(timer);
    }, [sessionReady, dataError, rebuild]);

    /* Theme pushes into the live iframe (same protocol as prebuilt previews). */
    useEffect(() => {
        if (sessionReady) {
            sessionRef.current?.setDark(darkMode);
        }
    }, [darkMode, sessionReady]);

    /* ------------------------------------------------------------------ */
    /* Editors                                                             */
    /* ------------------------------------------------------------------ */

    const editFile = (path: string, code: string) => {
        setFiles((current) => current.map((file) => (file.path === path ? { ...file, code } : file)));
    };

    const editData = (text: string) => {
        setDataText(text);

        try {
            JSON.parse(text);
            setDataError(null);
        } catch (error) {
            setDataError(error instanceof Error ? error.message : 'Invalid JSON');
        }
    };

    const reset = () => {
        setFiles(pristineRef.current.files);
        setDataText(pristineRef.current.dataText);
        setDataError(null);
    };

    /* ------------------------------------------------------------------ */
    /* Instant download of edits (SPEC §5.6 — sources, no server build)    */
    /* ------------------------------------------------------------------ */

    const download = async () => {
        if (payload === null) {
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
                body: JSON.stringify({
                    framework: 'react',
                    files: [...files, { path: `${payload.entry}/data.json`, code: dataText }],
                }),
            });

            if (!response.ok) {
                throw new Error(`Download failed with ${response.status}`);
            }

            const url = URL.createObjectURL(await response.blob());
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = `${component.basename}-react-edited.zip`;
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

    const tabs = [...files.map((file) => file.path), DATA_TAB];
    const isDataTab = activePath === DATA_TAB;
    const editorValue = isDataTab ? dataText : (files.find((file) => file.path === activePath)?.code ?? '');

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap gap-1" role="tablist" aria-label="Component files">
                    {tabs.map((path) => (
                        <button
                            key={path}
                            type="button"
                            role="tab"
                            aria-selected={activePath === path}
                            onClick={() => setActivePath(path)}
                            className={`rounded-md px-3 py-1.5 font-mono text-xs transition ${
                                activePath === path
                                    ? 'bg-neutral-900 text-white'
                                    : 'border border-neutral-200 bg-white text-neutral-600 hover:border-neutral-400 hover:text-neutral-900'
                            }`}
                        >
                            {path}
                        </button>
                    ))}
                </div>

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
                        disabled={downloading || dataError !== null}
                        className="inline-flex items-center gap-1.5 rounded-md bg-neutral-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-neutral-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {downloading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Download className="h-3.5 w-3.5" />}
                        Download edits
                    </button>
                </div>
            </div>

            {(buildError !== null || dataError !== null || sessionFailed !== null || downloadFailed) && (
                <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900" role="alert">
                    <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                    <div className="space-y-1 font-mono">
                        {sessionFailed !== null && <p>Live edit runtime failed to load: {sessionFailed}</p>}
                        {dataError !== null && <p>Sample data is not valid JSON: {dataError}</p>}
                        {buildError !== null && <p>{buildError}</p>}
                        {downloadFailed && <p>Download failed — please try again.</p>}
                    </div>
                </div>
            )}

            <div className="grid items-start gap-4 xl:grid-cols-2">
                <textarea
                    value={editorValue}
                    onChange={(event) => (isDataTab ? editData(event.target.value) : editFile(activePath, event.target.value))}
                    spellCheck={false}
                    aria-label={isDataTab ? 'Sample data JSON editor' : `Source editor for ${activePath}`}
                    className="h-[560px] w-full resize-none overflow-auto rounded-lg border border-neutral-200 bg-neutral-950 p-4 font-mono text-xs leading-5 text-neutral-100 outline-none focus:border-neutral-400"
                />

                <div className="relative rounded-xl border border-neutral-200 bg-neutral-100 p-4">
                    <iframe
                        ref={iframeRef}
                        title={`${component.name} live edit preview`}
                        sandbox="allow-scripts"
                        className="block w-full rounded-lg border border-neutral-200 bg-white"
                        style={{ height: previewHeight !== null ? `${previewHeight}px` : '480px' }}
                    />
                    {!sessionReady && sessionFailed === null && (
                        <div className="absolute inset-4 flex items-center justify-center gap-2 rounded-lg bg-white/90 text-xs font-medium text-neutral-500">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            Loading the live edit runtime…
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
