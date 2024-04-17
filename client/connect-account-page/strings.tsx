/* eslint-disable max-len */
/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import interpolateComponents from '@automattic/interpolate-components';

export default {
	button: {
		jetpack_not_connected: __(
			'Connect your store',
			'woocommerce-payments'
		),
		jetpack_connected: __(
			'Verify business details',
			'woocommerce-payments'
		),
		sandbox: __( 'Enable sandbox mode', 'woocommerce-payments' ),
	},
	heading: ( firstName?: string ): string =>
		sprintf(
			/* translators: %s: first name of the merchant, if it exists, %s: WooPayments. */
			__( 'Hi%s, Welcome to %s!', 'woocommerce' ),
			firstName ? ` ${ firstName }` : '',
			'WooPayments'
		),
	usp1: __(
		'Offer card payments, Apple Pay, iDeal, Affirm, Afterpay, and accept in-person payments with the Woo mobile app.',
		'woocommerce-payments'
	),
	usp2: __(
		'Sell to international markets and accept over 135 currencies with local payment methods.',
		'woocommerce-payments'
	),
	usp3: __(
		'Earn recurring revenue and get deposits into your bank account.',
		'woocommerce-payments'
	),
	sandboxMode: {
		title: __(
			"I'm setting up a store for someone else.",
			'woocommerce-payments'
		),
		description: sprintf(
			/* translators: %s: WooPayments */
			__(
				'This option will set up %s in sandbox mode. You can use our test data to set up. When you’re ready to launch your store, switching to live payments is easy.',
				'woocommerce-payments'
			),
			'WooPayments'
		),
	},
	sandboxModeNotice: interpolateComponents( {
		mixedString: __(
			'Sandbox mode is enabled, only test accounts will be created. If you want to process live transactions, please {{learnMoreLink}}disable it{{/learnMoreLink}}.',
			'woocommerce-payments'
		),
		components: {
			learnMoreLink: (
				// Link content is in the format string above. Consider disabling jsx-a11y/anchor-has-content.
				// eslint-disable-next-line jsx-a11y/anchor-has-content
				<a
					href="https://woocommerce.com/document/woopayments/testing-and-troubleshooting/sandbox-mode/"
					target="_blank"
					rel="noreferrer"
				/>
			),
		},
	} ),
	infoNotice: {
		description: {
			jetpack_connected: __(
				"You'll need to verify your business and payment details to ",
				'woocommerce-payments'
			),
			jetpack_not_connected: __(
				'To ensure safe and secure transactions, a WordPress.com account is required before you can verify your business details.',
				'woocommerce-payments'
			),
		},
		button: __( 'enable deposits.', 'woocommerce-payments' ),
	},
	infoModal: {
		title: sprintf(
			/* translators: %s: WooPayments */
			__( 'Verifying your information with %s', 'woocommerce-payments' ),
			'WooPayments'
		),
		whyWeAsk: {
			heading: __(
				'Why we ask for personal financial information',
				'woocommerce-payments'
			),
			description: sprintf(
				/* translators: %s: WooPayments */
				__(
					"As you continue the process of signing up for %s, we'll ask for information about your business, including the business owner's date of birth and tax ID number. We know you may wonder why we ask for this information, and how it will be used. The “Know Your Customer” process, explained below, helps us provide a safe, ethical environment for all financial transactions.",
					'woocommerce-payments'
				),
				'WooPayments'
			),
		},
		whatIsKyc: {
			heading: __(
				'What is “Know Your Customer”?',
				'woocommerce-payments'
			),
			description: __(
				"“Know Your Customer” standards are used by banks and other financial institutions to confirm that customers are who they say they are. By confirming their customers' identities, banks and financial institutions can help keep transactions safe from fraud and other suspicious activities.",
				'woocommerce-payments'
			),
		},
		whyShareInfo: {
			heading: __(
				'Why do I have to share this information?',
				'woocommerce-payments'
			),
			description: __(
				"Before we build a payment relationship with a customer, we ask for the information listed above to validate the business owner's identity and tax ID number, and to ensure that we can connect the listed bank account with the business itself.",
				'woocommerce-payments'
			),
			description2: __(
				'The ultimate goal of the “Know Your Customer” process is to help your business get up and running with payments as soon as possible while protecting your business and your customers. We follow the same regulations as other financial institutions so that we can ensure we operate in an ethical and trustworthy manner. We want to protect your business and the payments that we manage for you. The “Know Your Customer” process helps us protect you.',
				'woocommerce-payments'
			),
		},
		whatElse: {
			heading: __(
				'What else should I keep in mind while completing this process?',
				'woocommerce-payments'
			),
			description: sprintf(
				/* translators: %s: WooPayments */
				__(
					"If you're setting up %s for someone else, it's best to have that person complete the account creation process. As you can see above, we ask for very specific information about the business owner - and you might not have all the details at hand. It's not always possible to change account information once it's been saved, especially if the site accepts live transactions before the correct account information is entered.",
					'woocommerce-payments'
				),
				'WooPayments'
			),
		},
		isMyDataSafe: {
			heading: sprintf(
				/* translators: %s: WooPayments */
				__( 'Is my data safe with %s?', 'woocommerce-payments' ),
				'WooPayments'
			),
			description: sprintf(
				/* translators: %s: WooPayments */
				__(
					'We take every step required to safeguard your personal data. %s is built in partnership with Stripe to store your data in a safe and secure manner.',
					'woocommerce-payments'
				),
				'WooPayments'
			),
		},
		howQuickly: {
			heading: __(
				'How quickly will you confirm my identity and allow me to process payments?',
				'woocommerce-payments'
			),
			description: __(
				"We'll do our best to work with Stripe to confirm your identity as quickly as we can. Typically, we'll confirm your application within a couple of days.",
				'woocommerce-payments'
			),
		},
		whatInformation: {
			heading: __(
				'What information should I have at hand before I start the “Know Your Customer” process?',
				'woocommerce-payments'
			),
			description: __(
				"Here's a brief list of the information you'll need to finish payment signup:"
			),
		},
		businessOwnerInfo: {
			heading: __( 'Business owner info:', 'woocommerce-payments' ),
			fields: [
				__( 'Legal name', 'woocommerce-payments' ),
				__( 'Date of birth', 'woocommerce-payments' ),
				__( 'Home address', 'woocommerce-payments' ),
				__( 'Email address', 'woocommerce-payments' ),
				__( 'Mobile phone number', 'woocommerce-payments' ),
				__( 'Bank account information', 'woocommerce-payments' ),
				__(
					'Social Security number (SSN) or Taxpayer Identification Number',
					'woocommerce-payments'
				),
			],
		},
		businessInfo: {
			heading: __( 'Business info:', 'woocommerce-payments' ),
			fields: [
				__(
					'Country where your business is based',
					'woocommerce-payments '
				),
				__( 'Type of business', 'woocommerce-payments ' ),
				__( 'Industry', 'woocommerce-payments ' ),
				__( 'Company address', 'woocommerce-payments ' ),
				__( 'Company phone number', 'woocommerce-payments ' ),
				__( 'Company URL', 'woocommerce-payments ' ),
			],
		},
	},
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
		description: sprintf(
			/* translators: %s: WooPayments */
			__(
				'You’re ready to start using the features and benefits of %s.',
				'woocommerce-payments'
			),
			'WooPayments'
		),
	},
	onboardingDisabled: __(
		"We've temporarily paused new account creation. We'll notify you when we resume!",
		'woocommerce-payments'
	),
	incentive: {
		limitedTimeOffer: __( 'Limited time offer', 'woocommerce-payments' ),
		details: __(
			'Discount will be applied to payments processed via WooPayments upon completion of installation, setup, and connection.',
			'woocommerce-payments'
		),
		termsAndConditions: ( url: string ): JSX.Element =>
			createInterpolateElement(
				__(
					'*See <a>Terms and Conditions</a> for details.',
					'woocommerce-payments'
				),
				{
					a: (
						// eslint-disable-next-line jsx-a11y/anchor-has-content
						<a
							href={ url }
							target="_blank"
							rel="noopener noreferrer"
						/>
					),
				}
			),
		error: __(
			'There was an error applying the promotion. Please contact support for assistance if the problem persists',
			'woocommerce-payments'
		),
	},
	nonSupportedCountry: createInterpolateElement(
		sprintf(
			/* translators: %1$s: WooPayments */
			__(
				'<b>%1$s is not currently available in your location</b>. To be eligible for %1$s, your business address must be in one of the following <a>supported countries</a>.',
				'woocommerce-payments'
			),
			'WooPayments'
		),
		{
			b: <b />,
			a: (
				// eslint-disable-next-line jsx-a11y/anchor-has-content
				<a
					href="https://woocommerce.com/document/woopayments/compatibility/countries/"
					target="_blank"
					rel="noopener noreferrer"
				/>
			),
		}
	),
};
