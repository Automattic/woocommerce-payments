/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { getDepositMonthlyAnchorLabel } from 'wcpay/deposits/utils';
import { ExpressCheckouts, PaymentMethods } from './existing-controls';

export type Settings = Record< string, unknown >;

/** The parts of a field that PHP can't describe. They're merged into the field registered with the Fields API. */
export type FieldParts = {
	description?: React.ReactNode;
	elements?: { value: string | number; label: string }[];
	// eslint-disable-next-line @typescript-eslint/naming-convention -- DataForm field property.
	Edit?: React.ComponentType;
	isVisible?: ( item: Settings ) => boolean;
	isDisabled?: ( args: { item: Settings } ) => boolean;
};

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

export const getFieldParts = (): Record< string, FieldParts > => ( {
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
	is_payment_request_enabled: {
		Edit: ExpressCheckouts,
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
