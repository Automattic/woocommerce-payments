/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Button, CheckboxControl, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

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
	type: 'text' | 'boolean';
	// eslint-disable-next-line @typescript-eslint/naming-convention -- WordPress Fields API property.
	Edit: string | ( ( props: EditProps ) => JSX.Element );
	isVisible?: ( item: Settings ) => boolean;
	isDisabled?: ( args: { item: Settings; field: EntityField } ) => boolean;
};

declare global {
	const wcpaySettingsDataform: {
		classicUrl: string;
		woopayEligible: boolean;
		methodLabels: Record< string, string >;
	};
}

const settingsLink = ( anchor: string ) =>
	`${ wcpaySettingsDataform.classicUrl }#${ anchor }`;

const ConfirmedCheckbox = ( { data, field, onChange }: EditProps ) => {
	const [ confirming, setConfirming ] = useState( false );
	return (
		<>
			<CheckboxControl
				__nextHasNoMarginBottom
				label={ field.label }
				checked={ data[ field.id ] === true }
				disabled={ field.isDisabled?.( { item: data, field } ) }
				onChange={ ( checked ) =>
					checked
						? setConfirming( true )
						: onChange( { [ field.id ]: false } )
				}
			/>
			{ confirming && (
				<Modal
					title={ field.label }
					onRequestClose={ () => setConfirming( false ) }
				>
					<p>
						{ field.id === 'is_test_mode_enabled'
							? __(
									'Test mode uses test transactions instead of real payments.',
									'woocommerce-payments'
							  )
							: __(
									'Payments will need to be captured manually before their authorization expires.',
									'woocommerce-payments'
							  ) }
					</p>
					<Button
						variant="secondary"
						onClick={ () => setConfirming( false ) }
					>
						{ __( 'Cancel', 'woocommerce-payments' ) }
					</Button>
					<Button
						variant="primary"
						onClick={ () => {
							onChange( { [ field.id ]: true } );
							setConfirming( false );
						} }
					>
						{ __( 'Enable', 'woocommerce-payments' ) }
					</Button>
				</Modal>
			) }
		</>
	);
};

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

const FraudProtection = ( { data }: EditProps ) => (
	<>
		<p>
			{ __( 'Current protection level:', 'woocommerce-payments' ) }{ ' ' }
			{ String( data.current_protection_level ?? '' ) }
		</p>
		<a href={ settingsLink( 'fp-settings' ) }>
			{ __( 'Manage fraud protection', 'woocommerce-payments' ) }
		</a>
	</>
);

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

export const fields: EntityField[] = [
	checkbox(
		'is_wcpay_enabled',
		__( 'Enable WooPayments', 'woocommerce-payments' )
	),
	{
		...checkbox(
			'is_test_mode_enabled',
			__( 'Enable test mode', 'woocommerce-payments' )
		),
		Edit: ConfirmedCheckbox,
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
		Edit: ConfirmedCheckbox,
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
		label: __( 'Fraud protection', 'woocommerce-payments' ),
		type: 'text',
		Edit: FraudProtection,
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
