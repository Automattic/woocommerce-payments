/**
 * External dependencies
 */
import React from 'react';
import { createRoot } from 'react-dom/client';

/**
 * Internal dependencies
 */
import TestToLiveNotice from 'components/test-to-live-notice';

const mountTestToLiveNotice = (): void => {
	const container = document.getElementById( 'wcpay-test-to-live-notice' );
	if ( container ) {
		const root = createRoot( container );
		root.render( <TestToLiveNotice /> );
	}
};

if (
	document.readyState === 'interactive' ||
	document.readyState === 'complete'
) {
	mountTestToLiveNotice();
} else {
	window.addEventListener( 'DOMContentLoaded', mountTestToLiveNotice );
}
