/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

const enhancedFraudProtectionStep = {
	meta: {
		name: 'enhanced-fraud-protection',
		heading: __( 'Enhanced fraud protection is here 🔒' ),
		descriptions: {
			desktop: __(
				'You can now choose a level of protection to have for screening incoming transactions. You will then be able to review any caught transactions and select whether you would like to approve or decline them.'
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
			desktop: interpolateComponents( {
				mixedString: __(
					'Decide how aggressively you want to filter suspicious payments, from {{strong}}basic{{/strong}} to {{strong}}advanced{{/strong}}.'
				),
				components: { strong: <strong /> },
			} ),
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
				'We recommend using one of the preset risk levels, but if you need more control, head to Advanced to fine-tune the various filters.'
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
					"Payments that have been caught by a risk filter will appear under {{strong}}Payments > Transactions{{/strong}}. We'll let you know why each payment was flagged so that you can determine whether to approve or block it."
				),
				components: { strong: <strong /> },
			} ),
		},
	},
};

export const steps = [
	enhancedFraudProtectionStep,
	chooseYourFilterLevelStep,
	takeMoreControlStep,
	readyForReviewStep,
];
