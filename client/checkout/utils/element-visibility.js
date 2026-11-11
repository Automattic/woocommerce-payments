/**
 * Reports whether the element is in view each time that changes, until disconnected.
 *
 * The element counts as visible once at least `threshold` of it intersects the
 * viewport. Falls back to reporting the element as visible when
 * IntersectionObserver is unavailable, so features gated on visibility are
 * never permanently suppressed.
 *
 * @param {Element}  element            The element to watch.
 * @param {Function} onVisibilityChange Called with `true` or `false` whenever the element's visibility is reported.
 * @param {number}   threshold          Intersection ratio that counts as visible.
 * @return {Function} Disconnects the observer.
 */
export const observeElementVisibility = (
	element,
	onVisibilityChange,
	threshold = 0.1
) => {
	if ( ! element || typeof window.IntersectionObserver !== 'function' ) {
		onVisibilityChange( true );
		return () => {};
	}

	const observer = new window.IntersectionObserver(
		( entries ) => {
			const entry = entries[ entries.length - 1 ];
			onVisibilityChange(
				entry.isIntersecting && entry.intersectionRatio >= threshold
			);
		},
		{ threshold }
	);
	observer.observe( element );

	return () => observer.disconnect();
};
