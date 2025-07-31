/**
 * React hook for viewport detection
 * Based on wp-calypso viewport-react package
 */

/**
 * External dependencies
 */
import { useState, useEffect } from 'react';
import { throttle } from 'lodash';

/**
 * Internal dependencies
 */
import {
	getViewportSize,
	isVerySmallMobile,
	isMobile,
	isTablet,
	isDesktop,
} from '../utils/viewport';

export interface UseViewportReturn {
	viewportSize: { width: number; height: number };
	isVerySmallMobile: boolean;
	isMobile: boolean;
	isTablet: boolean;
	isDesktop: boolean;
}

const debounceTime = 300;

/**
 * Hook to get current viewport information
 * Updates when window is resized
 */
export function useViewport(): UseViewportReturn {
	const [ viewportSize, setViewportSize ] = useState( getViewportSize() );

	useEffect( () => {
		const handleResize = () => {
			setViewportSize( getViewportSize() );
		};

		const throttledHandleResize = throttle( handleResize, debounceTime );

		// Add event listener
		window.addEventListener( 'resize', throttledHandleResize );

		// Cleanup
		return () => {
			window.removeEventListener( 'resize', throttledHandleResize );
		};
	}, [] );

	return {
		viewportSize,
		isVerySmallMobile: isVerySmallMobile(),
		isMobile: isMobile(),
		isTablet: isTablet(),
		isDesktop: isDesktop(),
	};
}
