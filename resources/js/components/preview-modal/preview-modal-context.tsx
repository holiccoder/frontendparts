import type { ComponentDetailData } from '@/types/catalog';
import { createContext, useCallback, useContext, useState, type ReactNode } from 'react';
import { PreviewModalOverlay } from './preview-modal-overlay';

interface PreviewModalContextValue {
    /** Open the overlay for a component page URL (`/components/{usage}/{slug}`). */
    openPreview: (componentUrl: string) => void;
}

const PreviewModalContext = createContext<PreviewModalContextValue | null>(null);

/** Null outside a PreviewModalProvider — callers fall back to navigation. */
export function usePreviewModal(): PreviewModalContextValue | null {
    return useContext(PreviewModalContext);
}

interface OverlayState {
    open: boolean;
    loading: boolean;
    error: boolean;
    component: ComponentDetailData | null;
}

const CLOSED_STATE: OverlayState = { open: false, loading: false, error: false, component: null };

/**
 * Lets any catalog card open the preview modal as an overlay WITHOUT
 * navigation: card click → GET /api/components/{usage}/{slug} → dialog.
 * Mounted once inside the public layout; renders nothing extra during SSR.
 */
export function PreviewModalProvider({ children }: { children: ReactNode }) {
    const [state, setState] = useState<OverlayState>(CLOSED_STATE);

    const openPreview = useCallback((componentUrl: string) => {
        setState({ open: true, loading: true, error: false, component: null });

        // Card URLs are absolute (route()); the API endpoint is the same
        // path under /api.
        const path = componentUrl.startsWith('http') ? new URL(componentUrl).pathname : componentUrl;

        fetch(`/api${path}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Preview payload failed with ${response.status}`);
                }

                return response.json() as Promise<ComponentDetailData>;
            })
            .then((component) => {
                setState((current) => (current.open ? { open: true, loading: false, error: false, component } : current));
            })
            .catch(() => {
                setState((current) => (current.open ? { open: true, loading: false, error: true, component: null } : current));
            });
    }, []);

    const close = useCallback(() => setState(CLOSED_STATE), []);

    return (
        <PreviewModalContext.Provider value={{ openPreview }}>
            {children}
            <PreviewModalOverlay open={state.open} loading={state.loading} error={state.error} component={state.component} onClose={close} />
        </PreviewModalContext.Provider>
    );
}
