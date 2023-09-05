/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import React from 'react';

export default {
	steps: {
		mode: {
			heading: __(
				'Let’s get your store ready to accept payments',
				'woocommerce-payments'
			),
			subheading: __(
				'Select the option that best fits your needs.',
				'woocommerce-payments'
			),
			live: {
				label: __(
					'I’d like to set up payments on my own store',
					'woocommerce-payments'
				),
				note: __(
					'You’ll need to provide details to verify that you’re the owner of the account. If you’re setting up payments for someone else, choose the test payments option.',
					'woocommerce-payments'
				),
			},
			test: {
				label: __(
					'I’m building a store for someone else and would like to test payments',
					'woocommerce-payments'
				),
				note: sprintf(
					/* translators: %s: WooPayments */
					__(
						'This option will set up %s in development mode. You can use test data to set up, no personal information is required. When you’re ready to launch your store, switching to live payments is easy.',
						'woocommerce-payments'
					),
					'WooPayments'
				),
			},
			devModeNotice: interpolateComponents( {
				mixedString: __(
					'Dev mode is enabled. With Dev mode, only test accounts can be created that process test transactions. If you want to process live transactions, please disable Dev mode. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
					'woocommerce-payments'
				),
				components: {
					learnMoreLink: (
						// Link content is in the format string above. Consider disabling jsx-a11y/anchor-has-content.
						// eslint-disable-next-line jsx-a11y/anchor-has-content
						<a
							href={
								'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/dev-mode/'
							}
							target="_blank"
							rel="noreferrer"
						/>
					),
				},
			} ),
		},
		personal: {
			heading: __(
				'First, you’ll need to create an account',
				'woocommerce-payments'
			),
			subheading: __(
				'The information below should reflect that of the business owner or an authorized team member.',
				'woocommerce-payments'
			),
			notice: __(
				'We’ll use the email address to contact you with any important notifications related to your account, and the phone number will only be used to protect your account with two-factor authentication.',
				'woocommerce-payments'
			),
		},
		business: {
			heading: __(
				'Tell us about your business',
				'woocommerce-payments'
			),
			subheading: __(
				'We’ll use these details to enable payments for your store.',
				'woocommerce-payments'
			),
		},
		store: {
			heading: __(
				'Please share a few more details',
				'woocommerce-payments'
			),
			subheading: __(
				'This info will help us speed up the set up process.',
				'woocommerce-payments'
			),
		},
		loading: {
			heading: __(
				'Let’s get you set up for payments',
				'woocommerce-payments'
			),
			subheading: __(
				'Confirm your identity with our partner',
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
		'individual.first_name': __(
			'Please provide a first name',
			'woocommerce-payments'
		),
		'individual.last_name': __(
			'Please provide a last name',
			'woocommerce-payments'
		),
		email: __( 'Please provide a valid email', 'woocommerce-payments' ),
		phone: __(
			'Please provide a valid phone number',
			'woocommerce-payments'
		),
		url: __( 'Please provide a valid website', 'woocommerce-payments' ),
		business_name: __(
			'Please provide a business name',
			'woocommerce-payments'
		),
		country: __( 'Please provide a country', 'woocommerce-payments' ),
		business_type: __(
			'Please provide a business type',
			'woocommerce-payments'
		),
		mcc: __(
			'Please provide a type of goods or services',
			'woocommerce-payments'
		),
	},
	placeholders: {
		country: __(
			'Select the primary country of your business',
			'woocommerce-payments'
		),
		business_type: __(
			'Select the legal structure of your business',
			'woocommerce-payments'
		),
		'company.structure': __(
			'Select the legal category of your business',
			'woocommerce-payments'
		),
		mcc: __(
			'Select the primary industry of your business',
			'woocommerce-payments'
		),
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
	restoredState: __(
		'We have restored your previous session. You can pick up where you left off, or go back to a previous step to make changes. ',
		'woocommerce-payments'
	),
	continue: __( 'Continue', 'woocommerce-payments' ),
	back: __( 'Back', 'woocommerce-payments' ),
};
