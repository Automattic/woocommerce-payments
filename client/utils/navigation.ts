/**
 * Navigates the browser to a URL.
 *
 * Extracted so callers don't need to assign to `window.location.href` inside
 * component bodies or hooks — the `react-hooks/immutability` rule treats that
 * assignment as a mutation of a captured value — and so that tests can observe
 * the navigation at all: jsdom marks `window.location` unforgeable, so it can
 * be neither redefined nor spied on.
 *
 * This lives in its own module rather than in `client/utils/index.js` so that
 * checkout bundles can import it without pulling in the whole utils barrel.
 *
 * @param url The URL to navigate to.
 */
export const redirectTo = ( url: string ): void => {
	window.location.href = url;
};
