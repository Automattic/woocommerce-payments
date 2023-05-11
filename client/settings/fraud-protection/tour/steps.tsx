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
				'You can now choose a level of protection for screening incoming transactions. You can then review any flagged transactions and decide to approve or block them.'
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
				"Choose how you'd like to filter suspicious transactions, from Basic to Advanced."
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
				'Choose Advanced settings for full control over each filter. You can enable and configure filters and choose an action between risk review or block.'
			),
		},
	},
};

const readyForReviewStep = {
	referenceElements: {
		desktop: '#toplevel_page_wc-admin-path--payments-overview',
	},
	meta: {
		name: 'ready-for-review',
		heading: __( 'Ready for review 📥️' ),
		descriptions: {
			desktop: interpolateComponents( {
				mixedString: __(
					"Payments that have been caught by a risk filter will appear in {{strong}}Payments > Transactions{{/strong}}. We'll let you know why each payment was flagged so you can determine whether to approve or block it."
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
