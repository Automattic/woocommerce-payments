/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';

export default {
	heading: __( 'WooCommerce Payments', 'woocommerce-payments' ),
	description: __(
		'Accept credit card payments the easy way! No set up fees. No monthly fees. Just 2.9% + $0.30 per transaction on U.S.-issued cards.',
		'woocommerce-payments'
	),
	terms: createInterpolateElement(
		__(
			'By clicking “Set up,” you agree to the <a>Terms of Service</a>',
			'woocommerce-payments'
		),
		{
			// eslint-disable-next-line jsx-a11y/anchor-has-content
			a: <a href="https://wordpress.com/tos" />,
		}
	),
	onboardingDisabled: [
		__(
			"We've temporarily paused new account creation.",
			'woocommmerce-payments'
		),
		__( "We'll notify you when we resume!", 'woocommmerce-payments' ),
	],
};
