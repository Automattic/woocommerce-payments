/**
 * External dependencies
 */
import React from 'react';
import { Button, ExternalLink } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import { getDepositMonthlyAnchorLabel } from 'wcpay/deposits/utils';
import { PaymentMethods } from './existing-controls';
import TestAccountNotice from './test-account-notice';

export type Settings = Record< string, unknown >;

/** The parts of a field that PHP can't describe. They're merged into the field registered with the Fields API. */
export type FieldParts = {
	description?: React.ReactNode;
	elements?: {
		value: string | number;
		label: string;
		description?: string;
	}[];
	// eslint-disable-next-line @typescript-eslint/naming-convention -- DataForm field property.
	Edit?: React.ComponentType;
	isVisible?: ( item: Settings ) => boolean;
	isDisabled?: ( args: { item: Settings } ) => boolean;
	render?: React.ComponentType< { item: Settings } >;
	getValue?: ( args: { item: Settings } ) => unknown;
};

declare global {
	const wcpaySettingsScreenConfig: {
		fraudRulesUrl: string;
	};
}

const isDevMode = ( { item }: { item: Settings } ) =>
	item.is_dev_mode_enabled === true;

const isTestAccount = ( item: Settings ) =>
	item.is_test_mode_onboarding === true;

const isJapaneseAccount = ( item: Settings ) => item.account_country === 'JP';

// Matches the existing settings page, which hides the schedule when payouts are restricted or new.
const isPayoutScheduleLocked = ( { item }: { item: Settings } ) =>
	item.deposit_status !== 'enabled' ||
	item.deposit_restrictions === 'schedule_restricted' ||
	item.deposit_completed_waiting_period !== true;

// Matches the notes under the payout schedule on the existing settings page.
const getPayoutIntervalNote = ( interval: unknown ) =>
	( (
		{
			daily: __(
				'Payouts will occur every business day.',
				'woocommerce-payments'
			),
			weekly: __(
				'Payouts that fall on a holiday will initiate on the next business day.',
				'woocommerce-payments'
			),
			monthly: __(
				'Payouts scheduled on a weekend will be sent on the next business day.',
				'woocommerce-payments'
			),
		} as Record< string, string >
	 )[ String( interval ) ] );

/** A card's description, shown by a read-only field since DataForm card descriptions can only be text. */
const CardDescription = ( {
	text,
	link,
}: {
	text: string;
	link?: { href: string; label: string };
} ) => (
	<p className="wcpay-settings-screen__card-description">
		{ text }
		{ link && (
			<>
				{ ' ' }
				<ExternalLink href={ link.href }>{ link.label }</ExternalLink>
			</>
		) }
	</p>
);

const getCardDescriptions = (): Record< string, FieldParts > => ( {
	test_mode_description: {
		render: () => (
			<CardDescription
				text={ __(
					'Test mode allows you to place test orders and issue refunds without using real payment details.',
					'woocommerce-payments'
				) }
				link={ {
					href: 'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/',
					label: __(
						'Learn more about test mode',
						'woocommerce-payments'
					),
				} }
			/>
		),
	},
	fraud_protection_description: {
		render: () => (
			<CardDescription
				text={ __(
					'Help avoid unauthorized transactions and disputes by setting your fraud protection level.',
					'woocommerce-payments'
				) }
				link={ {
					href: 'https://woocommerce.com/document/woopayments/fraud-and-disputes/fraud-protection/',
					label: __(
						'Learn more about fraud protection',
						'woocommerce-payments'
					),
				} }
			/>
		),
	},
	payouts_description: {
		render: ( { item } ) => (
			<>
				<CardDescription
					text={ sprintf(
						/* translators: %s: number of business days. */
						__(
							'Funds are available for payout %s business days after they’re received.',
							'woocommerce-payments'
						),
						String( item.deposit_delay_days ?? '' )
					) }
					link={ {
						href: 'https://woocommerce.com/document/woopayments/payouts/payout-schedule/',
						label: __(
							'Learn more about payout schedules',
							'woocommerce-payments'
						),
					} }
				/>
				{ getPayoutIntervalNote( item.deposit_schedule_interval ) && (
					<p className="wcpay-settings-screen__card-description">
						{ getPayoutIntervalNote(
							item.deposit_schedule_interval
						) }
					</p>
				) }
			</>
		),
	},
	payout_bank_account: {
		render: () => (
			<div className="wcpay-settings-screen__bank-account">
				<p className="wcpay-settings-screen__bank-account-title">
					{ __( 'Manage your bank account', 'woocommerce-payments' ) }
				</p>
				<CardDescription
					text={ __(
						'Manage and update your bank account information to receive payouts.',
						'woocommerce-payments'
					) }
					link={
						wcpaySettings.accountStatus?.accountLink
							? {
									href: wcpaySettings.accountStatus
										.accountLink,
									label: __(
										'Manage in Stripe',
										'woocommerce-payments'
									),
							  }
							: undefined
					}
				/>
			</div>
		),
	},
	transactions_description: {
		render: () => (
			<CardDescription
				text={ __(
					"Update your store's configuration to ensure smooth transactions.",
					'woocommerce-payments'
				) }
				link={ {
					href: 'https://woocommerce.com/document/woopayments/',
					label: __(
						'View our documentation',
						'woocommerce-payments'
					),
				} }
			/>
		),
	},
	account_notifications_description: {
		render: () => (
			<CardDescription
				text={ `${ __(
					'Receive important notifications about your WooPayments account.',
					'woocommerce-payments'
				) } ${ __(
					'Anyone with access to this email address will be treated as the account owner. Please verify the address carefully.',
					'woocommerce-payments'
				) }` }
				link={ {
					href: 'https://woocommerce.com/document/woopayments/settings-guide/#account-notifications',
					label: __( 'Learn more', 'woocommerce-payments' ),
				} }
			/>
		),
	},
	customer_facing_details_description: {
		render: () => (
			<CardDescription
				text={ __(
					'Update the details your customers see on their bank statement and when they reach out for support.',
					'woocommerce-payments'
				) }
			/>
		),
	},
} );

export const getFieldParts = (): Record< string, FieldParts > => ( {
	...getCardDescriptions(),
	is_test_mode_enabled: {
		// Test accounts and development mode can only take test payments, so test mode stays on.
		getValue: ( { item } ) =>
			isTestAccount( item ) ||
			item.is_dev_mode_enabled === true ||
			item.is_test_mode_enabled === true,
		isDisabled: ( { item } ) =>
			isTestAccount( item ) || item.is_dev_mode_enabled === true,
	},
	test_account_notice: {
		render: TestAccountNotice,
		isVisible: isTestAccount,
	},
	current_protection_level: {
		elements: [
			{
				value: 'basic',
				label: __( 'Basic', 'woocommerce-payments' ),
				description: __(
					"Provides basic anti-fraud protection only. Payments will be blocked if the card's issuing bank cannot verify the CVV.",
					'woocommerce-payments'
				),
			},
			{
				value: 'advanced',
				label: __( 'Advanced', 'woocommerce-payments' ),
				description: __(
					'Allows you to fine-tune the level of filtering according to your business needs.',
					'woocommerce-payments'
				),
			},
		],
		isDisabled: ( { item } ) =>
			item.advanced_fraud_protection_settings === 'error',
	},
	fraud_rules_link: {
		render: () => (
			<Button
				variant="secondary"
				className="wcpay-settings-screen__fraud-rules-link"
				href={ wcpaySettingsScreenConfig.fraudRulesUrl }
			>
				{ __( 'Configure', 'woocommerce-payments' ) }
			</Button>
		),
		isVisible: ( item ) => item.current_protection_level === 'advanced',
	},
	enabled_payment_method_ids: {
		Edit: PaymentMethods,
	},
	is_manual_capture_enabled: {
		description: interpolateComponents( {
			mixedString: __(
				'Issue an authorization on checkout and capture later. {{learnMoreLink}}Learn more{{/learnMoreLink}}.',
				'woocommerce-payments'
			),
			components: {
				learnMoreLink: (
					// @ts-expect-error: children is provided when interpolating the component
					<ExternalLink href="https://woocommerce.com/document/woopayments/settings-guide/authorize-and-capture/" />
				),
			},
		} ),
		isDisabled: ( { item } ) => item.is_stripe_billing_enabled === true,
	},
	account_statement_descriptor_kanji: {
		isVisible: isJapaneseAccount,
	},
	account_statement_descriptor_kana: {
		isVisible: isJapaneseAccount,
	},
	deposit_schedule_interval: {
		description: __(
			'Payout scheduling is available once payouts are enabled and the 7-day waiting period for new accounts is complete.',
			'woocommerce-payments'
		),
		// An account without a schedule has no interval, which would fail validation and block saving.
		isVisible: ( item ) => Boolean( item.deposit_schedule_interval ),
		isDisabled: isPayoutScheduleLocked,
	},
	deposit_schedule_weekly_anchor: {
		isVisible: ( item ) => item.deposit_schedule_interval === 'weekly',
		isDisabled: isPayoutScheduleLocked,
	},
	deposit_schedule_monthly_anchor: {
		// Days 1 to 28, and 31 for the last day of the month.
		elements: [
			...Array.from( { length: 28 }, ( unused, index ) => index + 1 ),
			31,
		].map( ( anchor ) => ( {
			value: anchor,
			label: getDepositMonthlyAnchorLabel( { monthlyAnchor: anchor } ),
		} ) ),
		isVisible: ( item ) => item.deposit_schedule_interval === 'monthly',
		isDisabled: isPayoutScheduleLocked,
	},
	is_multi_currency_enabled: {
		description: interpolateComponents( {
			mixedString: __(
				'Allow customers to shop and pay in multiple currencies. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
				'woocommerce-payments'
			),
			components: {
				learnMoreLink: (
					// @ts-expect-error: children is provided when interpolating the component
					<ExternalLink href="https://woocommerce.com/document/woopayments/currencies/multi-currency-setup/" />
				),
			},
		} ),
	},
	is_stripe_billing_enabled: {
		description: interpolateComponents( {
			mixedString: sprintf(
				__(
					'Future %s subscriptions will use Stripe Billing, which charges a 1%% fee on the full total of any order' +
						' containing a subscription. It supports card payments only and does not support all' +
						' WooCommerce Subscriptions features. {{learnMoreLink}}Learn more about fees{{/learnMoreLink}}',
					'woocommerce-payments'
				),
				'WooPayments'
			),
			components: {
				learnMoreLink: (
					// @ts-expect-error: children is provided when interpolating the component
					<ExternalLink href="https://woocommerce.com/document/woopayments/fees/" />
				),
			},
		} ),
		isVisible: () => wcpaySettings.isStripeBillingEligible === true,
	},
	is_debug_log_enabled: {
		isDisabled: isDevMode,
	},
} );
