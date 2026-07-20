/**
 * Fire-and-forget copy-event ping (SPEC §8.6): POSTs to the component's copy
 * endpoint without blocking the clipboard interaction. `keepalive` lets the
 * request survive navigations; failures are swallowed — analytics must never
 * break the copy UX. The XSRF-TOKEN cookie is Laravel's JS-readable CSRF
 * handshake, present for guests and users alike.
 */
export function trackCopyEvent(url: string): void {
    const token = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );

    fetch(url, {
        method: 'POST',
        headers: { 'X-XSRF-TOKEN': token, Accept: 'application/json' },
        credentials: 'same-origin',
        keepalive: true,
    }).catch(() => {
        // Analytics ping failed — nothing sensible to surface to the user.
    });
}
