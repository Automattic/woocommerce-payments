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

// Wait for DOM to be ready
window.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById(
		'wcpay-payment-settings-spotlight'
	);

	if ( container ) {
		const root = createRoot( container );
		root.render( <SpotlightPromotion /> );
	}
} );
