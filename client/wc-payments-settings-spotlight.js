/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { createRoot } from 'react-dom/client';

/**
 * Internal dependencies
 */
import SpotlightPromotion from 'promotions/spotlight';

/**
 * Mounts the SpotlightPromotion component to the DOM.
 */
const mountSpotlightPromotion = () => {
	const container = document.getElementById(
		'wcpay-payments-settings-spotlight'
	);

	if ( container ) {
		const root = createRoot( container );
		root.render( <SpotlightPromotion /> );
	}
};

// Mount immediately if DOM is already ready, otherwise wait for DOMContentLoaded
if (
	document.readyState === 'interactive' ||
	document.readyState === 'complete'
) {
	mountSpotlightPromotion();
} else {
	window.addEventListener( 'DOMContentLoaded', mountSpotlightPromotion );
}
