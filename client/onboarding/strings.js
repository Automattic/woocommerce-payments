/** @format */

/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export default {
	button: __( 'Finish setup', 'woocommerce-payments' ),

	heading: __( 'WooCommerce Payments', 'woocommerce-payments' ),

	learnMore: __( 'Learn more', 'woocommerce-payments' ),

	onboarding: {
		heading: __(
			'Tell us more about your business',
			'woocommerce-payments'
		),
		description: __(
			"Preview the details we'll require to verify your business and enable despoits.",
			'woocommerce-payments'
		),

		countryDescription: __(
			'The primary country where your business operates',
			'woocommerce-payments'
		),
	},

	businessTypes: {
		individual: __( 'Individual', 'woocommerce-payments' ),
		company: __( 'Company', 'woocommerce-payments' ),
		non_profit: __( 'Non-Profit', 'woocommerce-payments' ),
		government_entity: __( 'Government Entity', 'woocommerce-payments' ),
	},
};
