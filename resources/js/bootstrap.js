/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

// Global helper to retrieve active cookie by name
window.getCookie = function (name) {
    if (typeof document === 'undefined' || !document.cookie) return '';
    const cookies = document.cookie.split('; ');
    for (const c of cookies) {
        const [k, ...rest] = c.split('=');
        if (k === name) {
            return decodeURIComponent(rest.join('='));
        }
    }
    return '';
};
