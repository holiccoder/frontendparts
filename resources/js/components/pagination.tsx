import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/shared';
import { Link } from '@inertiajs/react';

/**
 * Renders the Laravel paginator's link window (prev/next + page numbers)
 * for server-paginated catalog grids.
 */
export function Pagination({ meta }: { meta: Paginated<unknown>['meta'] }) {
    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <nav className="mt-10 flex items-center justify-center gap-1" aria-label="Pagination">
            {meta.links.map((link, index) => {
                const classes = cn(
                    'inline-flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-sm font-medium transition',
                    link.active
                        ? 'bg-neutral-900 text-white'
                        : link.url
                          ? 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900'
                          : 'cursor-default text-neutral-300',
                );

                return link.url ? (
                    <Link key={index} href={link.url} className={classes} dangerouslySetInnerHTML={{ __html: link.label }} preserve-scroll />
                ) : (
                    <span key={index} className={classes} dangerouslySetInnerHTML={{ __html: link.label }} />
                );
            })}
        </nav>
    );
}
