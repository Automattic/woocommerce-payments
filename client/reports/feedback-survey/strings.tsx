/**
 * External dependencies
 */
import React from 'react';
import { createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export const feedbackQuestion = __(
	'Did this report give you the information you needed?',
	'woocommerce-payments'
);

export const thumbsUpLabel = __(
	'What did you use this report for? (optional)',
	'woocommerce-payments'
);

export const thumbsDownLabel = __(
	"What's missing? (optional)",
	'woocommerce-payments'
);

export const thumbsUpAriaLabel = __(
	'This report was helpful',
	'woocommerce-payments'
);

export const thumbsDownAriaLabel = __(
	'This report was not helpful',
	'woocommerce-payments'
);

export const reportFeedbackRatingAriaLabel = __(
	'Report feedback rating',
	'woocommerce-payments'
);

export const closeAriaLabel = __(
	'Dismiss feedback survey',
	'woocommerce-payments'
);
export const cancelLabel = __( 'Cancel', 'woocommerce-payments' );
export const sendLabel = __( 'Send', 'woocommerce-payments' );
export const submitErrorMessage = __(
	'Your feedback could not be sent. Please try again.',
	'woocommerce-payments'
);
export const submitSuccessMessage = __(
	'Thanks for your feedback!',
	'woocommerce-payments'
);

export const privacyDisclaimer = createInterpolateElement(
	__(
		'Your feedback will only be shared with WooCommerce and treated pursuant to our <a>privacy policy</a>.',
		'woocommerce-payments'
	),
	{
		a: (
			// eslint-disable-next-line jsx-a11y/anchor-has-content
			<a
				href="https://automattic.com/privacy/"
				target="_blank"
				rel="noopener noreferrer"
			/>
		),
	}
);
