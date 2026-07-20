import { useEffect, useState } from 'react';

/** Desktop gate (SPEC §5.6): the runtime renders only at desktop widths —
 * the same `lg` breakpoint the modal uses for its split layout. */
const DESKTOP_QUERY = '(min-width: 1024px)';

/** Shared by the React and Vue edit surfaces (Phase 3.1 / 3.2). */
export function useIsDesktop(): boolean {
    const [isDesktop, setIsDesktop] = useState(false);

    useEffect(() => {
        const mql = window.matchMedia(DESKTOP_QUERY);

        const onChange = () => setIsDesktop(mql.matches);

        mql.addEventListener('change', onChange);
        setIsDesktop(mql.matches);

        return () => mql.removeEventListener('change', onChange);
    }, []);

    return isDesktop;
}
