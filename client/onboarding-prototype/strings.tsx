/* eslint-disable max-len */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

export default {
	steps: {
		mode: {
			heading: __(
				'Let’s get your store ready to accept payments.',
				'woocommerce-payments'
			),
			subheading: __(
				'What would you like to do?',
				'woocommerce-payments'
			),
			live: {
				label: __(
					'I’m ready to setup payments for my store',
					'woocommerce-payments'
				),
				paymentMethods: __(
					'Offer card payments, Apple Pay, Sofort, iDeal and many more.',
					'woocommerce-payments'
				),
				internationalMarkets: __(
					'Sell to international markets and accept over 135 currencies with local payment methods.',
					'woocommerce-payments'
				),
				managePayments: __(
					'Manage payments and your store all from one place.',
					'woocommerce-payments'
				),
				tip: createInterpolateElement(
					__(
						'In order to receive payouts with <a>WooCommerce Payments</a>, you’ll need to share business details to verify who you are.',
						'woocommerce-payments'
					),
					{
						a: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content
							<a
								href="https://woocommerce.com/payments/"
								target="_blank"
								rel="noopener noreferrer"
							/>
						),
					}
				),
				tos: createInterpolateElement(
					__(
						'By clicking “Continue” you agree to the <a>Terms of Service</a>.',
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
			},
			test: {
				label: __(
					'I’d like to setup test payments',
					'woocommerce-payments'
				),
				setup: __(
					'Setup WooCommerce Payments in test mode. ',
					'woocommerce-payments'
				),
				testData: createInterpolateElement(
					__(
						'No need to use actual information, just setup with <a>our test data</a>.',
						'woocommerce-payments'
					),
					{
						a: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content
							<a
								href="https://woocommerce.com/document/woocommerce-payments/testing-and-troubleshooting/dev-mode/"
								target="_blank"
								rel="noopener noreferrer"
							/>
						),
					}
				),
				payments: __(
					'Test WooCommerce Payments on a site that will not need to process real payments.',
					'woocommerce-payments'
				),
				tip: __(
					'Note: You will be redirected to our partner’s website to setup a test account.',
					'woocommerce-payments'
				),
			},
		},
		personal: {
			heading: __( 'Tell us about yourself', 'woocommerce-payments' ),
			subheading: __(
				'The information below should reflect that of the business owner or a significant shareholder.',
				'woocommerce-payments'
			),
			notice: __(
				'We will use this email address to contact you with any important notifications or information related to your account.',
				'woocommerce-payments'
			),
		},
		business: {
			heading: __(
				'Tell us about your business',
				'woocommerce-payments'
			),
			subheading: __(
				'We will use these details to enable payments for your store.',
				'woocommerce-payments'
			),
		},
		store: {
			heading: __(
				'Tell us more about your business',
				'woocommerce-payments'
			),
			subheading: __(
				'This information will assist us in getting you set up quickly.',
				'woocommerce-payments'
			),
		},
	},
	fields: {
		email: __( 'What’s your email address?', 'woocommerce-payments' ),
		'individual.first_name': __( 'First name', 'woocommerce-payments' ),
		'individual.last_name': __( 'Last name', 'woocommerce-payments' ),
		phone: __( 'What’s your mobile phone number?', 'woocommerce-payments' ),
		business_name: __(
			'What’s the legal name of your business?',
			'woocommerce-payments'
		),
		url: __( 'What’s your business website?', 'woocommerce-payments' ),
		country: __(
			'Where is your business legally registered?',
			'woocommerce-payments'
		),
		business_type: __(
			'What type of legal entity is your business?',
			'woocommerce-payments'
		),
		'company.structure': __(
			'What category of legal entity identify your business?',
			'woocommerce-payments'
		),
		mcc: __(
			'What type of goods or services does your business sell? ',
			'woocommerce-payments'
		),
		annual_revenue: __(
			'What is your estimated annual Ecommerce revenue (USD)?',
			'woocommerce-payments'
		),
		go_live_timeframe: __(
			'What is the estimated timeline for taking your store live?',
			'woocommerce-payments'
		),
	},
	errors: {
		generic: __( 'Please provide a response', 'woocommerce-payments' ),
		email: __( 'Please provide a valid email', 'woocommerce-payments' ),
		phone: __(
			'Please provide a valid phone number',
			'woocommerce-payments'
		),
		url: __( 'Please provide a valid website', 'woocommerce-payments' ),
	},
	placeholders: {
		country: __( 'Select a location', 'woocommerce-payments' ),
		business_type: __(
			'Select the legal structure of your business',
			'woocommerce-payments'
		),
		'company.structure': __(
			'Select the legal category of your business',
			'woocommerce-payments'
		),
		mcc: __( 'Please select your industry', 'woocommerce-payments' ),
		annual_revenue: __(
			'Select your annual revenue',
			'woocommerce-payments'
		),
		go_live_timeframe: __( 'Select a timeline', 'woocommerce-payments' ),
	},
	annualRevenues: {
		less_than_250k: __( 'Less than $250k', 'woocommerce-payments' ),
		from_250k_to_1m: __( '$250k - $1M', 'woocommerce-payments' ),
		from_1m_to_20m: __( '$1M - $20M', 'woocommerce-payments' ),
		from_20m_to_100m: __( '$20M - $100M', 'woocommerce-payments' ),
		more_than_100m: __( 'More than $100M', 'woocommerce-payments' ),
	},
	goLiveTimeframes: {
		already_live: __( 'My store is already live', 'woocommerce-payments' ),
		within_1month: __( 'Within 1 month', 'woocommerce-payments' ),
		from_1_to_3months: __( '1 – 3 months', 'woocommerce-payments' ),
		from_3_to_6months: __( '3 – 6 months', 'woocommerce-payments' ),
		more_than_6months: __( '6+ months', 'woocommerce-payments' ),
	},
	continue: __( 'Continue', 'woocommerce-payments' ),
};
