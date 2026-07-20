import { xsrfToken } from '@/lib/xsrf';

/**
 * Fire-and-forget copy-event ping (SPEC §8.6): POSTs to the component's copy
 * endpoint without blocking the clipboard interaction. `keepalive` lets the
 * request survive navigations; failures are swallowed — analytics must never
 * break the copy UX.
 */
export function trackCopyEvent(url: string): void {
    fetch(url, {
        method: 'POST',
        headers: { 'X-XSRF-TOKEN': xsrfToken(), Accept: 'application/json' },
        credentials: 'same-origin',
        keepalive: true,
    }).catch(() => {
        // Analytics ping failed — nothing sensible to surface to the user.
    });
}
