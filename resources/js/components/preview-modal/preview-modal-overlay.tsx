import type { ComponentDetailData } from '@/types/catalog';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import { PreviewModal } from './preview-modal';

interface PreviewModalOverlayProps {
    open: boolean;
    loading: boolean;
    error: boolean;
    component: ComponentDetailData | null;
    onClose: () => void;
}

/**
 * Overlay variant host (SPEC §5.4): radix Dialog supplies the focus trap,
 * esc handling, aria labelling and body scroll lock; the inner content is
 * the SAME PreviewModal the detail page renders inline.
 */
export function PreviewModalOverlay({ open, loading, error, component, onClose }: PreviewModalOverlayProps) {
    return (
        <DialogPrimitive.Root
            open={open}
            onOpenChange={(nextOpen) => {
                if (!nextOpen) {
                    onClose();
                }
            }}
        >
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="data-[state=open]:animate-in data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/60 backdrop-blur-sm" />
                <DialogPrimitive.Content
                    onOpenAutoFocus={(event) => event.preventDefault()}
                    className="fixed inset-3 z-50 flex flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-2xl focus:outline-none sm:inset-6 lg:inset-x-16 lg:inset-y-8"
                >
                    <DialogPrimitive.Title className="sr-only">{component ? `${component.name} preview` : 'Component preview'}</DialogPrimitive.Title>

                    {loading && (
                        <div className="flex flex-1 flex-col" aria-busy="true">
                            <div className="flex items-center justify-between gap-4 border-b border-neutral-100 px-6 py-4">
                                <div className="h-6 w-48 animate-pulse rounded bg-neutral-100" />
                                <div className="h-9 w-64 animate-pulse rounded bg-neutral-100" />
                            </div>
                            <div className="flex flex-1 items-center justify-center p-6">
                                <div className="h-full max-h-[520px] w-full animate-pulse rounded-xl bg-neutral-100" />
                            </div>
                        </div>
                    )}

                    {error && !loading && (
                        <div className="flex flex-1 flex-col items-center justify-center gap-3 p-10 text-center">
                            <p className="text-sm font-semibold text-neutral-900">Couldn’t load this component</p>
                            <p className="text-xs text-neutral-500">The preview payload failed to load. Please try again.</p>
                            <button
                                type="button"
                                onClick={onClose}
                                className="mt-2 inline-flex items-center gap-1.5 rounded-md border border-neutral-300 px-3.5 py-2 text-sm font-medium text-neutral-700 transition hover:border-neutral-400"
                            >
                                <X className="h-4 w-4" />
                                Close
                            </button>
                        </div>
                    )}

                    {component && !loading && (
                        <PreviewModal component={component} initialFramework="react" variant="overlay" onClose={onClose} className="min-h-0 flex-1" />
                    )}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
