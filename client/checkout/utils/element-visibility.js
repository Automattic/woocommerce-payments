/**
 * Calls `onVisible` once, the first time the element intersects the viewport.
 *
 * Falls back to calling `onVisible` immediately when IntersectionObserver is
 * unavailable, so features gated on visibility are never permanently suppressed.
 *
 * @param {Element}  element   The element to watch.
 * @param {Function} onVisible Called once when the element comes into view.
 * @param {number}   threshold Intersection ratio that counts as visible.
 * @return {Function} Disconnects the observer.
 */
export const observeElementVisibility = (
	element,
	onVisible,
	threshold = 0.1
) => {
	if ( ! element || typeof window.IntersectionObserver !== 'function' ) {
		onVisible();
		return () => {};
	}

	const observer = new window.IntersectionObserver(
		( entries ) => {
			if ( entries.some( ( entry ) => entry.isIntersecting ) ) {
				observer.disconnect();
				onVisible();
			}
		},
		{ threshold }
	);
	observer.observe( element );

	return () => observer.disconnect();
};
