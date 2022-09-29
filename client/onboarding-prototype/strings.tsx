/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export default {
	regularOnboardingNotice: __(
		'Full KYC process on Stripe',
		'woocommerce-payments'
	),
	connect: __( 'Connect', 'woocommerce-payments' ),
	progressiveOnboarding: __(
		'Progressive Onboarding',
		'woocommerce-payments'
	),
	submit: __( 'Submit', 'woocommerce-payments' ),
	countries: {
		usa: __( 'USA', 'woocommerce-payments' ),
	},
	businessTypes: {
		individual: __( 'Individual', 'woocommerce-payments' ),
	},
	mcc: {
		computerSoftware: __( 'Computer Software', 'woocommerce-payments' ),
		books: __( 'Books', 'woocommerce-payments' ),
	},
	controls: {
		country: __( 'Country', 'woocommerce-payments' ),
		businessType: __( 'Business type', 'woocommerce-payments' ),
		businessName: __( 'Business name', 'woocommerce-payments' ),
		url: __( 'Site URL', 'woocommerce-payments' ),
		mcc: __( 'Business industry', 'woocommerce-payments' ),
		email: __( 'Email address', 'woocommerce-payments' ),
		firstName: __( 'First name', 'woocommerce-payments' ),
		lastName: __( 'Last name', 'woocommerce-payments' ),
	},
};
