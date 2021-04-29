/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export default {
	button: __( 'Finish Setup', 'woocommerce-payments' ),

	heading: __( 'WooCommerce Payments', 'woocommerce-payments' ),

	learnMore: __( 'Learn more', 'woocommerce-payments' ),

	onboarding: {
		heading: __(
			'Finish setup to enable credit card payments',
			'woocommerce-payments'
		),
		description: __(
			'With WooCommerce Payments, you can securely accept major cards, Apple Pay, and payments in over 100 currencies. Track cash flow and manage recurring revenue directly from your store’s dashboard - with no setup costs or monthly fees.',
			'woocommerce-payments'
		),
	},

	paymentMethodsHeading: __(
		'Accepted payment methods',
		'woocommerce-payments'
	),

	recommended: __( 'Recommended', 'woocommerce-payments' ),

	stepsHeading: __(
		'You’re only steps away from getting paid',
		'woocommerce-payments'
	),

	step1: {
		heading: __(
			'Create and connect your account',
			'woocommerce-payments'
		),
		description: __(
			'To ensure safe and secure transactions, a WordPress.com account is required.',
			'woocommerce-payments'
		),
	},

	step2: {
		heading: __( 'Provide a few business details', 'woocommerce-payments' ),
		description: __(
			'Next we’ll ask you to verify your business and payment details to enable deposits.',
			'woocommerce-payments'
		),
	},

	step3: {
		heading: __( 'Setup complete!', 'woocommerce-payments' ),
		description: __(
			'You’re ready to start using the features and benefits of WooCommerce Payments.'
		),
	},

	onboardingDisabled: [
		__(
			"We've temporarily paused new account creation.",
			'woocommerce-payments'
		),
		__( "We'll notify you when we resume!", 'woocommerce-payments' ),
	],
};
