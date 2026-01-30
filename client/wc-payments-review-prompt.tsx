/**
 * External dependencies
 */
import React from 'react';
import { createRoot } from 'react-dom/client';

/**
 * Internal dependencies
 */
import ReviewPrompt from 'review-prompt';

const mountReviewPrompt = (): void => {
	const container = document.getElementById( 'wcpay-review-prompt' );
	if ( container ) {
		const root = createRoot( container );
		root.render( <ReviewPrompt /> );
	}
};

if (
	document.readyState === 'interactive' ||
	document.readyState === 'complete'
) {
	mountReviewPrompt();
} else {
	window.addEventListener( 'DOMContentLoaded', mountReviewPrompt );
}
