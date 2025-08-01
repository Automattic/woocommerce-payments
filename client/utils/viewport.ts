/**
 * Viewport utilities for responsive design
 * Based on wp-calypso viewport package
 */

export interface ViewportSize {
	width: number;
	height: number;
}

export interface ViewportBreakpoint {
	name: string;
	width: number;
}

// Define breakpoints (matching the existing breakpoints in the codebase)
export const BREAKPOINTS: ViewportBreakpoint[] = [
	{ name: 'mobile', width: 480 },
	{ name: 'tablet', width: 660 },
	{ name: 'desktop', width: 800 },
	{ name: 'wide', width: 960 },
	{ name: 'huge', width: 1040 },
	{ name: 'massive', width: 1280 },
	{ name: 'colossal', width: 1400 },
];

/**
 * Get current viewport size
 */
export function getViewportSize(): ViewportSize {
	return {
		width: window.innerWidth,
		height: window.innerHeight,
	};
}

/**
 * Check if viewport width is less than a specific breakpoint
 */
export function isViewportWidthLessThan( breakpoint: number ): boolean {
	return getViewportSize().width < breakpoint;
}

/**
 * Check if viewport width is greater than or equal to a specific breakpoint
 */
export function isViewportWidthGreaterThanOrEqualTo(
	breakpoint: number
): boolean {
	return getViewportSize().width >= breakpoint;
}

/**
 * Get the current breakpoint name based on viewport width
 */
export function getCurrentBreakpoint(): string {
	const width = getViewportSize().width;

	// Find the largest breakpoint that the width is greater than or equal to
	for ( let i = BREAKPOINTS.length - 1; i >= 0; i-- ) {
		if ( width >= BREAKPOINTS[ i ].width ) {
			return BREAKPOINTS[ i ].name;
		}
	}

	// If width is less than the smallest breakpoint, return mobile
	return 'mobile';
}

/**
 * Check if current viewport matches a specific breakpoint
 */
export function isBreakpoint( breakpointName: string ): boolean {
	return getCurrentBreakpoint() === breakpointName;
}

/**
 * Check if viewport is mobile (width < 480px)
 */
export function isMobile(): boolean {
	return isViewportWidthLessThan( 480 );
}

/**
 * Check if viewport is tablet (480px <= width < 800px)
 */
export function isTablet(): boolean {
	return (
		isViewportWidthGreaterThanOrEqualTo( 480 ) &&
		isViewportWidthLessThan( 800 )
	);
}

/**
 * Check if viewport is desktop (width >= 800px)
 */
export function isDesktop(): boolean {
	return isViewportWidthGreaterThanOrEqualTo( 800 );
}

/**
 * Check if viewport is very small mobile (width < 385px)
 */
export function isVerySmallMobile(): boolean {
	return isViewportWidthLessThan( 385 );
}
