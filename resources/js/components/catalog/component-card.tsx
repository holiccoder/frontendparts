import { AccessBadge, LevelBadge } from '@/components/catalog/badges';
import { usePreviewModal } from '@/components/preview-modal';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import type { ComponentCardData } from '@/types/catalog';
import { Link } from '@inertiajs/react';
import type { MouseEvent } from 'react';

export function ComponentCard({ component }: { component: ComponentCardData }) {
    const previewModal = usePreviewModal();

    // Plain left-click opens the preview overlay without navigation;
    // modified clicks (ctrl/middle/new tab) keep the native link behavior.
    const handleClick = (event: MouseEvent<HTMLAnchorElement>) => {
        if (!previewModal || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
            return;
        }

        event.preventDefault();
        previewModal.openPreview(component.url);
    };

    return (
        <Link
            href={component.url}
            onClick={handleClick}
            className="group flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white transition hover:border-neutral-300 hover:shadow-[0_8px_30px_rgb(0,0,0,0.06)]"
        >
            <div className="relative aspect-[16/10] overflow-hidden border-b border-neutral-100 bg-neutral-50">
                {component.thumb ? (
                    <img
                        src={component.thumb}
                        alt={`${component.name} preview`}
                        loading="lazy"
                        className="h-full w-full object-cover object-top transition duration-300 group-hover:scale-[1.02]"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center">
                        <PlaceholderPattern className="h-full w-full stroke-neutral-300" />
                    </div>
                )}
                <div className="absolute top-2 right-2">
                    <AccessBadge access={component.access} />
                </div>
            </div>

            <div className="flex flex-1 flex-col gap-2 p-4">
                <div className="flex items-start justify-between gap-3">
                    <h3 className="text-sm font-semibold text-neutral-900">{component.name}</h3>
                    <LevelBadge level={component.level} className="shrink-0" />
                </div>
                <span className="inline-flex w-fit items-center rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600">
                    {component.usage.name}
                </span>
            </div>
        </Link>
    );
}

export function ComponentGrid({ components }: { components: ComponentCardData[] }) {
    return (
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {components.map((component) => (
                <ComponentCard key={component.id} component={component} />
            ))}
        </div>
    );
}
