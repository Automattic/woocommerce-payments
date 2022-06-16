/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

export default {
	heading: __(
		'Accept payments and manage your business.',
		'woocommerce-payments'
	),
	description: createInterpolateElement(
		__(
			'By using WooCommerce Payments you agree to be bound by our <a1>Terms of Service</a1> and acknowledge that you have read our <a2>Privacy Policy</a2>',
			'woocommerce-payments'
		),
		{
			a1: (
				// eslint-disable-next-line jsx-a11y/anchor-has-content
				<a
					href="https://wordpress.com/tos"
					target="_blank"
					rel="noopener noreferrer"
				/>
			),
			a2: (
				// eslint-disable-next-line jsx-a11y/anchor-has-content
				<a
					href="https://automattic.com/privacy/"
					target="_blank"
					rel="noopener noreferrer"
				/>
			),
		}
	),
	button: __( 'Get started', 'woocommerce-payments' ),
	acceptedPaymentMethods: __(
		'Accepted payment methods include:',
		'woocommerce-payments'
	),
	detailsPayments: __(
		'Offer your customers their preferred way to pay including debit and credit card payments, Apple Pay, Sofort, SEPA, iDeal and many more.',
		'woocommerce-payments'
	),
	detailsMulticurrency: __(
		'Sell to international markets and accept more than 135 currencies with local payment methods.',
		'woocommerce-payments'
	),
	detailsManage: __(
		'Earn and manage recurring revenue and get automatic deposits into your nominated bank account.',
		'woocommerce-payments'
	),
	onboardingDisabled: __(
		"We've temporarily paused new account creation. We'll notify you when we resume!",
		'woocommerce-payments'
	),
};
