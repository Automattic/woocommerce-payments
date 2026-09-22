/**
 * External dependencies
 */
import React from 'react';
import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { ProtectionLevel } from '../settings/fraud-protection/advanced-settings/constants';

export type Settings = Record< string, unknown >;
type EditProps = {
	data: Settings;
	field: EntityField;
	onChange: ( edits: Settings ) => void;
};

/** The field contract supplied to the POC's private editor registry. */
export type EntityField = {
	id: string;
	label: string;
	description?: string | JSX.Element;
	elements?: { value: string; label: string }[];
	type: 'text' | 'boolean';
	// eslint-disable-next-line @typescript-eslint/naming-convention -- WordPress Fields API property.
	Edit: string | ( ( props: EditProps ) => JSX.Element );
	isVisible?: ( item: Settings ) => boolean;
	isDisabled?: ( args: { item: Settings; field: EntityField } ) => boolean;
};

declare global {
	const wcpaySettingsDataform: {
		classicUrl: string;
		fraudRulesUrl: string;
		woopayEligible: boolean;
		methodLabels: Record< string, string >;
	};
}

const settingsLink = ( anchor: string ) =>
	`${ wcpaySettingsDataform.classicUrl }#${ anchor }`;

/** Preserve selections outside the control being edited. */
export const togglePaymentMethod = (
	selected: string[],
	id: string,
	checked: boolean
) =>
	checked
		? Array.from( new Set( [ ...selected, id ] ) )
		: selected.filter( ( value ) => value !== id );

const PaymentMethods = ( { data, onChange }: EditProps ) => {
	const selected = data.enabled_payment_method_ids as string[];
	const available = data.available_payment_method_ids as string[];
	return (
		<fieldset>
			<legend>
				{ __( 'Enabled payment methods', 'woocommerce-payments' ) }
			</legend>
			{ available.map( ( id ) => (
				<CheckboxControl
					__nextHasNoMarginBottom
					key={ id }
					label={ wcpaySettingsDataform.methodLabels[ id ] ?? id }
					checked={ selected.includes( id ) }
					disabled={ id === 'card' && selected.includes( id ) }
					onChange={ ( checked ) =>
						onChange( {
							enabled_payment_method_ids: togglePaymentMethod(
								selected,
								id,
								checked
							),
						} )
					}
				/>
			) ) }
			<p>
				<a href={ settingsLink( 'payment-methods' ) }>
					{ __(
						'Manage payment method activation and eligibility',
						'woocommerce-payments'
					) }
				</a>
			</p>
		</fieldset>
	);
};

const ExpressCheckout = ( props: EditProps ) => (
	<>
		<CheckboxControl
			__nextHasNoMarginBottom
			label={ props.field.label }
			checked={ props.data[ props.field.id ] === true }
			onChange={ ( value ) =>
				props.onChange( { [ props.field.id ]: value } )
			}
		/>
		<a
			href={ `${ wcpaySettingsDataform.classicUrl }&method=${
				props.field.id === 'is_woopay_enabled'
					? 'woopay'
					: 'payment_request'
			}` }
		>
			{ __( 'Customize', 'woocommerce-payments' ) }
		</a>
	</>
);

const Payouts = ( { data }: EditProps ) => (
	<>
		<p>
			{ __( 'Current payout schedule:', 'woocommerce-payments' ) }{ ' ' }
			{ String( data.deposit_schedule_interval ?? '' ) }
		</p>
		<a href={ settingsLink( 'payout-schedule' ) }>
			{ __(
				'Manage payout schedule and bank account',
				'woocommerce-payments'
			) }
		</a>
	</>
);

const checkbox = ( id: string, label: string ): EntityField => ( {
	id,
	label,
	type: 'boolean',
	Edit: 'checkbox',
} );
const text = ( id: string, label: string ): EntityField => ( {
	id,
	label,
	type: 'text',
	Edit: 'text',
} );

export const getFields = (): EntityField[] => [
	checkbox(
		'is_wcpay_enabled',
		__( 'Enable WooPayments', 'woocommerce-payments' )
	),
	{
		...checkbox(
			'is_test_mode_enabled',
			__( 'Enable test mode', 'woocommerce-payments' )
		),
		description: __(
			'Test mode uses test transactions instead of real payments. Click Save changes to apply.',
			'woocommerce-payments'
		),
		isDisabled: ( { item } ) => item.is_dev_mode_enabled === true,
		isVisible: ( data ) => data.is_test_mode_onboarding !== true,
	},
	{
		id: 'enabled_payment_method_ids',
		label: __( 'Payment methods', 'woocommerce-payments' ),
		type: 'text',
		Edit: PaymentMethods,
	},
	{
		...checkbox(
			'is_payment_request_enabled',
			__( 'Apple Pay / Google Pay', 'woocommerce-payments' )
		),
		Edit: ExpressCheckout,
	},
	{
		...checkbox(
			'is_woopay_enabled',
			__( 'WooPay', 'woocommerce-payments' )
		),
		Edit: ExpressCheckout,
		isVisible: () => wcpaySettingsDataform.woopayEligible,
	},
	checkbox(
		'is_saved_cards_enabled',
		__( 'Enable payments via saved cards', 'woocommerce-payments' )
	),
	{
		...checkbox(
			'is_manual_capture_enabled',
			__( 'Enable manual capture', 'woocommerce-payments' )
		),
		description: __(
			'Payments must be captured manually before their authorization expires. Click Save changes to apply.',
			'woocommerce-payments'
		),
		isDisabled: ( { item } ) => item.is_stripe_billing_enabled === true,
	},
	text(
		'account_statement_descriptor',
		__( 'Customer bank statement', 'woocommerce-payments' )
	),
	{
		...text(
			'account_statement_descriptor_kanji',
			__( 'Statement descriptor (Kanji)', 'woocommerce-payments' )
		),
		isVisible: ( data ) => data.account_country === 'JP',
	},
	{
		...text(
			'account_statement_descriptor_kana',
			__( 'Statement descriptor (Kana)', 'woocommerce-payments' )
		),
		isVisible: ( data ) => data.account_country === 'JP',
	},
	text(
		'account_business_support_email',
		__( 'Support email', 'woocommerce-payments' )
	),
	text(
		'account_business_support_phone',
		__( 'Support phone', 'woocommerce-payments' )
	),
	{
		id: 'deposit_schedule_interval',
		label: __( 'Payout schedule', 'woocommerce-payments' ),
		type: 'text',
		Edit: Payouts,
	},
	text(
		'account_communications_email',
		__( 'Account email', 'woocommerce-payments' )
	),
	{
		id: 'current_protection_level',
		label: __( 'Protection level', 'woocommerce-payments' ),
		type: 'text',
		Edit: 'radio',
		elements: [
			{
				value: ProtectionLevel.BASIC,
				label: __( 'Basic', 'woocommerce-payments' ),
			},

			{
				value: ProtectionLevel.ADVANCED,
				label: __( 'Advanced', 'woocommerce-payments' ),
			},
		],
		isDisabled: ( { item } ) =>
			item.advanced_fraud_protection_settings === 'error',
		description: (
			<>
				{ __(
					'Advanced uses your configured rules. Save your selection before opening the rule editor.',
					'woocommerce-payments'
				) }{ ' ' }
				<a href={ wcpaySettingsDataform.fraudRulesUrl }>
					{ __( 'Configure advanced rules', 'woocommerce-payments' ) }
				</a>{ ' ' }
			</>
		),
	},
	checkbox(
		'is_multi_currency_enabled',
		__( 'Enable multi-currency', 'woocommerce-payments' )
	),
	{
		...checkbox(
			'is_debug_log_enabled',
			__( 'Enable debug logging', 'woocommerce-payments' )
		),
		isDisabled: ( { item } ) => item.is_dev_mode_enabled === true,
	},
];
