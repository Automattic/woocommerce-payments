/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

const enhancedFraudProtectionStep = {
	referenceElements: {
		desktop: '#fraud-protection-welcome-tour-first-step',
	},
	meta: {
		name: 'enhanced-fraud-protection',
		heading: __( 'Enhanced fraud protection is here 🔒' ),
		descriptions: {
			desktop: __(
				'You can now choose a level of protection for screening incoming transactions. Screened transactions are automatically blocked.'
			),
		},
		primaryButton: {
			text: __( "See what's new" ),
		},
	},
};

const chooseYourFilterLevelStep = {
	referenceElements: {
		desktop: '#fraud-protection-card-title',
	},
	meta: {
		name: 'choose-your-filter-level',
		heading: __( 'Choose your filter level 🚦' ),
		descriptions: {
			desktop: __(
				"Choose how you'd like to screen incoming transactions using our Basic or Advanced options."
			),
		},
	},
};

const takeMoreControlStep = {
	referenceElements: {
		desktop: '#fraud-protection-level-select_advanced-level',
	},
	meta: {
		name: 'take-more-control',
		heading: __( 'Take more control 🎚️' ),
		descriptions: {
			desktop: __(
				'Choose Advanced settings for full control over each filter. You can enable and configure filters to block risky transactions.'
			),
		},
	},
};

const readyForReviewStep = {
	referenceElements: {
		desktop: '#toplevel_page_wc-admin-path--payments-overview',
	},
	meta: {
		name: 'review-blocked-transactions',
		heading: __( 'Review blocked transactions 📥️' ),
		descriptions: {
			desktop: interpolateComponents( {
				mixedString: __(
					"Payments that have been blocked by a risk filter will appear under the blocked tab in {{strong}}Payments > Transactions{{/strong}}. We'll let you know why each payment was blocked so you can determine if you need to adjust your risk filters."
				),
				components: { strong: <strong /> },
			} ),
		},
		primaryButton: {
			text: __( 'Got it' ),
		},
	},
};

export const steps = [
	enhancedFraudProtectionStep,
	chooseYourFilterLevelStep,
	takeMoreControlStep,
	readyForReviewStep,
];
