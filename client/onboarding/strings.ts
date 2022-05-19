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
			'Preview the details we may require to verify your business and enable deposits.',
			'woocommerce-payments'
		),

		countryDescription: __(
			'The primary country where your business operates',
			'woocommerce-payments'
		),

		requirementsDescription: __(
			'Verifying your details can require:',
			'woocommerce-payments'
		),
	},
};
