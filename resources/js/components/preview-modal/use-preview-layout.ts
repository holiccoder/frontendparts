import type { PreviewLayoutPreference, SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

const DEFAULT_LAYOUT: PreviewLayoutPreference = { side: 'left', split: 30 };
const STORAGE_KEY = 'fp:preview-layout';
const PATCH_DEBOUNCE_MS = 500;

function clampSplit(split: number): number {
    return Math.min(80, Math.max(20, Math.round(split)));
}

function normalize(value: unknown): PreviewLayoutPreference | null {
    if (typeof value !== 'object' || value === null) {
        return null;
    }

    const candidate = value as Record<string, unknown>;

    if (candidate.side !== 'left' && candidate.side !== 'right') {
        return null;
    }

    const split = Number(candidate.split);

    if (!Number.isFinite(split)) {
        return null;
    }

    return { side: candidate.side, split: clampSplit(split) };
}

/**
 * Editable preview-modal pane layout (SPEC §5.4): structure/content pane can
 * sit left or right of the stage with a 20–80% drag split. Guests persist to
 * localStorage; authenticated users also PATCH settings.preview-layout
 * (debounced), and the server-shared preference wins on first paint.
 * localStorage is read post-mount so SSR output stays deterministic.
 */
export function usePreviewLayout() {
    const { auth } = usePage<SharedData>().props;
    const serverLayout = normalize(auth.preview_layout);
    const isAuthenticated = Boolean(auth.user);

    const [layout, setLayout] = useState<PreviewLayoutPreference>(serverLayout ?? DEFAULT_LAYOUT);
    const patchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        if (serverLayout) {
            return;
        }

        try {
            const stored = normalize(JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? 'null'));

            if (stored) {
                setLayout(stored);
            }
        } catch {
            // Corrupted payload — keep the default layout.
        }
        // Only on mount: the server preference is fixed for the page lifetime.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        return () => {
            if (patchTimer.current) {
                clearTimeout(patchTimer.current);
            }
        };
    }, []);

    const persist = useCallback(
        (next: PreviewLayoutPreference) => {
            setLayout(next);

            try {
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
            } catch {
                // Private mode / quota — the server copy still applies.
            }

            if (!isAuthenticated) {
                return;
            }

            if (patchTimer.current) {
                clearTimeout(patchTimer.current);
            }

            patchTimer.current = setTimeout(() => {
                router.patch('/settings/preview-layout', next, {
                    preserveState: true,
                    preserveScroll: true,
                });
            }, PATCH_DEBOUNCE_MS);
        },
        [isAuthenticated],
    );

    const setSide = useCallback((side: 'left' | 'right') => persist({ ...layout, side }), [layout, persist]);
    const setSplit = useCallback((split: number) => persist({ ...layout, split: clampSplit(split) }), [layout, persist]);

    return { layout, setSide, setSplit };
}
