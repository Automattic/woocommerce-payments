/**
 * External dependencies
 */
import React from 'react';
import { ExternalLink } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { getDepositMonthlyAnchorLabel } from 'wcpay/deposits/utils';
import { PaymentMethods } from './existing-controls';

export type Settings = Record< string, unknown >;

/** The parts of a field that PHP can't describe. They're merged into the field registered with the Fields API. */
export type FieldParts = {
	description?: React.ReactNode;
	elements?: { value: string | number; label: string }[];
	// eslint-disable-next-line @typescript-eslint/naming-convention -- DataForm field property.
	Edit?: React.ComponentType;
	isVisible?: ( item: Settings ) => boolean;
	isDisabled?: ( args: { item: Settings } ) => boolean;
	render?: React.ComponentType< { item: Settings } >;
	getValue?: ( args: { item: Settings } ) => unknown;
	setValue?: ( args: { item: Settings; value: unknown } ) => Settings;
};

/** Shows an express checkout at one location, stored as the method's ID in that location's list of methods. */
export const expressCheckoutLocation = (
	method: string,
	location: string
): FieldParts => {
	const key = `express_checkout_${ location }_methods`;
	const methods = ( item: Settings ) =>
		Array.isArray( item[ key ] ) ? ( item[ key ] as string[] ) : [];
	return {
		getValue: ( { item } ) => methods( item ).includes( method ),
		setValue: ( { item, value } ) => ( {
			[ key ]: [
				...methods( item ).filter( ( entry ) => entry !== method ),
				...( value ? [ method ] : [] ),
			],
		} ),
	};
};

const getExpressCheckoutLocations = (): Record< string, FieldParts > =>
	Object.fromEntries(
		[ 'woopay', 'payment_request', 'amazon_pay' ].flatMap( ( method ) =>
			[ 'product', 'cart', 'checkout' ].map( ( location ) => [
				`${ method }_on_${ location }`,
				expressCheckoutLocation( method, location ),
			] )
		)
	);

declare global {
	const wcpaySettingsScreenConfig: {
		fraudRulesUrl: string;
	};
}

const isDevMode = ( { item }: { item: Settings } ) =>
	item.is_dev_mode_enabled === true;

const isJapaneseAccount = ( item: Settings ) => item.account_country === 'JP';

// Matches the existing settings page, which hides the schedule when payouts are restricted or new.
const isPayoutScheduleLocked = ( { item }: { item: Settings } ) =>
	item.deposit_status !== 'enabled' ||
	item.deposit_restrictions === 'schedule_restricted' ||
	item.deposit_completed_waiting_period !== true;

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
				text={ __(
					'Receive important notifications about your WooPayments account.',
					'woocommerce-payments'
				) }
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
	advanced_description: {
		render: () => (
			<CardDescription
				text={ __(
					'More options for specific payment needs.',
					'woocommerce-payments'
				) }
				link={ {
					href: 'https://woocommerce.com/document/woopayments/settings-guide/#advanced-settings',
					label: __(
						'View our documentation',
						'woocommerce-payments'
					),
				} }
			/>
		),
	},
} );

export const getFieldParts = (): Record< string, FieldParts > => ( {
	...getCardDescriptions(),
	...getExpressCheckoutLocations(),
	is_test_mode_enabled: {
		isVisible: ( item ) => item.is_test_mode_onboarding !== true,
		isDisabled: isDevMode,
	},
	current_protection_level: {
		description: (
			<a href={ wcpaySettingsScreenConfig.fraudRulesUrl }>
				{ __( 'Configure advanced rules', 'woocommerce-payments' ) }
			</a>
		),
		isDisabled: ( { item } ) =>
			item.advanced_fraud_protection_settings === 'error',
	},
	enabled_payment_method_ids: {
		Edit: PaymentMethods,
	},
	is_manual_capture_enabled: {
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
	is_stripe_billing_enabled: {
		isVisible: () => wcpaySettings.isStripeBillingEligible === true,
	},
	is_debug_log_enabled: {
		isDisabled: isDevMode,
	},
} );
