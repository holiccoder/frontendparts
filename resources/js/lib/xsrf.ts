/**
 * Laravel's JS-readable CSRF handshake: the XSRF-TOKEN cookie, present for
 * guests and users alike. Read it for same-origin POST fetches.
 */
export function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}
