import { cn } from '@/lib/utils';
import type { AccessLevel, ComponentLevel } from '@/types/catalog';

const LEVEL_STYLES: Record<ComponentLevel, string> = {
    element: 'bg-sky-50 text-sky-700 ring-sky-600/20',
    block: 'bg-violet-50 text-violet-700 ring-violet-600/20',
    section: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    page: 'bg-amber-50 text-amber-800 ring-amber-600/25',
};

export function LevelBadge({ level, className }: { level: ComponentLevel; className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold tracking-wide uppercase ring-1 ring-inset',
                LEVEL_STYLES[level],
                className,
            )}
        >
            {level}
        </span>
    );
}

export function AccessBadge({ access, className }: { access: AccessLevel; className?: string }) {
    const isPaid = access === 'paid';

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold tracking-wide uppercase ring-1 ring-inset',
                isPaid ? 'bg-neutral-900 text-white ring-neutral-900' : 'bg-white text-neutral-600 ring-neutral-300',
                className,
            )}
        >
            {isPaid ? 'Pro' : 'Free'}
        </span>
    );
}
