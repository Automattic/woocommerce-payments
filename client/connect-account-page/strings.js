/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';

export default {
	heading: __( 'WooCommerce Payments', 'woocommerce-payments' ),
	description: createInterpolateElement(
		__(
			'Accept credit card payments the easy way! <a>No set up fees. No monthly fees.</a>',
			'woocommerce-payments'
		),
		{
			a: (
				// eslint-disable-next-line jsx-a11y/anchor-has-content
				<a
					href="https://docs.woocommerce.com/document/payments/faq/fees/"
					target="_blank"
					rel="noopener noreferrer"
				/>
			),
		}
	),
	terms: createInterpolateElement(
		__(
			'By clicking “Set up,” you agree to the <a>Terms of Service</a>',
			'woocommerce-payments'
		),
		{
			a: (
				// eslint-disable-next-line jsx-a11y/anchor-has-content
				<a
					href="https://wordpress.com/tos"
					target="_blank"
					rel="noopener noreferrer"
				/>
			),
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
