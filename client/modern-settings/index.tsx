/**
 * External dependencies
 */
import React, { useMemo, useState } from 'react';
import { BaseControl, Button, CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { RawHTML } from '@wordpress/element';

/**
 * Internal dependencies
 */
import FraudPreventionSettingsContext from '../settings/fraud-protection/advanced-settings/context';
import AVSMismatchRuleCard from '../settings/fraud-protection/advanced-settings/cards/avs-mismatch';
import CVCVerificationRuleCard from '../settings/fraud-protection/advanced-settings/cards/cvc-verification';
import InternationalIPAddressRuleCard from '../settings/fraud-protection/advanced-settings/cards/international-ip-address';
import IPAddressMismatchRuleCard from '../settings/fraud-protection/advanced-settings/cards/ip-address-mismatch';
import AddressMismatchRuleCard from '../settings/fraud-protection/advanced-settings/cards/address-mismatch';
import PurchasePriceThresholdRuleCard from '../settings/fraud-protection/advanced-settings/cards/purchase-price-threshold';
import OrderItemsThresholdRuleCard from '../settings/fraud-protection/advanced-settings/cards/order-items-threshold';
import { ProtectionLevel } from '../settings/fraud-protection/advanced-settings/constants';
import {
	readRuleset,
	writeRuleset,
} from '../settings/fraud-protection/advanced-settings/utils';
import { ProtectionSettingsUI } from '../settings/fraud-protection/interfaces';
import './style.scss';

type SettingsFieldValue = string | number | boolean | string[] | null;

type SettingsFieldComponentProps = {
	field: {
		id: string;
		label: string;
		type: string;
		description?: string;
		options?: Array< {
			label: string;
			value: string;
			description?: string;
			icon?: string;
			category?: string;
		} >;
		fields?: Array< {
			id: string;
			label: string;
			type: string;
			value?: SettingsFieldValue;
		} >;
		disabled?: boolean;
	};
	value: SettingsFieldValue;
	values: Record< string, SettingsFieldValue >;
	onChange: ( value: SettingsFieldValue ) => void;
	onFieldChange: ( fieldId: string, value: SettingsFieldValue ) => void;
};

declare global {
	interface Window {
		wcModernSettings?: {
			registerSettingsExtension?: ( config: {
				scope: {
					page: string;
					section?: string;
				};
				components?: Record<
					string,
					( props: SettingsFieldComponentProps ) => JSX.Element | null
				>;
			} ) => void;
		};
	}
}

const PaymentMethodsField = ( {
	field,
	value,
	onChange,
}: SettingsFieldComponentProps ) => {
	const selectedValues = Array.isArray( value ) ? value : [];
	const category =
		field.id === 'upe_enabled_payment_method_ids_bnpl'
			? 'buy-now-pay-later'
			: 'standard';
	const options = ( field.options || [] ).filter(
		( option ) => ( option.category || 'standard' ) === category
	);

	const updateSelectedValues = ( optionValue: string, checked: boolean ) => {
		if ( checked ) {
			onChange(
				Array.from( new Set( [ ...selectedValues, optionValue ] ) )
			);
			return;
		}

		onChange(
			selectedValues.filter(
				( selectedValue ) => selectedValue !== optionValue
			)
		);
	};

	if ( options.length === 0 ) {
		return null;
	}

	return (
		<div className="woopayments-modern-settings-card woopayments-modern-settings-payment-methods">
			{ options.map( ( option ) => {
				const checked = selectedValues.includes( option.value );
				const isLockedCard = option.value === 'card' && checked;

				return (
					<div
						key={ option.value }
						className="woopayments-modern-settings-payment-method"
					>
						<CheckboxControl
							className="woopayments-modern-settings-payment-method__checkbox"
							label=""
							checked={ checked }
							disabled={ field.disabled || isLockedCard }
							onChange={ ( nextChecked ) =>
								updateSelectedValues(
									option.value,
									nextChecked
								)
							}
							__nextHasNoMarginBottom
						/>
						<div className="woopayments-modern-settings-payment-method__icon">
							{ option.icon ? (
								<img src={ option.icon } alt="" />
							) : (
								<span>{ option.label.slice( 0, 2 ) }</span>
							) }
						</div>
						<div className="woopayments-modern-settings-payment-method__body">
							<div className="woopayments-modern-settings-payment-method__title">
								{ option.label }
								{ option.value === 'card' ? (
									<span>
										{ __(
											' (Required)',
											'woocommerce-payments'
										) }
									</span>
								) : null }
							</div>
							{ option.description ? (
								<div className="woopayments-modern-settings-payment-method__description">
									<RawHTML>{ option.description }</RawHTML>
								</div>
							) : null }
							{ isLockedCard ? (
								<div className="woopayments-modern-settings-payment-method__description">
									{ __(
										'Card payments are required for WooPayments.',
										'woocommerce-payments'
									) }
								</div>
							) : null }
						</div>
					</div>
				);
			} ) }
		</div>
	);
};

const PayoutScheduleField = () => (
	<div className="woopayments-modern-settings-card">
		<h3>{ __( 'Payout schedule', 'woocommerce-payments' ) }</h3>
		<div className="woopayments-modern-settings-radio-row">
			<input type="radio" checked readOnly />
			<div>
				<strong>{ __( 'Automatic', 'woocommerce-payments' ) }</strong>
				<div className="woopayments-modern-settings-inline-controls">
					<BaseControl
						id="woopayments-payout-frequency"
						label={ __( 'Frequency', 'woocommerce-payments' ) }
						__nextHasNoMarginBottom
					>
						<select id="woopayments-payout-frequency" disabled>
							<option>
								{ __( 'Monthly', 'woocommerce-payments' ) }
							</option>
						</select>
					</BaseControl>
					<BaseControl
						id="woopayments-payout-date"
						label={ __( 'Date', 'woocommerce-payments' ) }
						__nextHasNoMarginBottom
					>
						<select id="woopayments-payout-date" disabled>
							<option>
								{ __( '17th', 'woocommerce-payments' ) }
							</option>
						</select>
					</BaseControl>
				</div>
				<p>
					{ __(
						'Deposits that fall on a holiday or a weekend will initiate on the next business day.',
						'woocommerce-payments'
					) }
				</p>
			</div>
		</div>
		<div className="woopayments-modern-settings-radio-row">
			<input type="radio" readOnly />
			<div>
				<strong>{ __( 'Manual', 'woocommerce-payments' ) }</strong>
				<p>
					{ __(
						'Create a deposit from the Payments Overview. Deposits are available once per day, with a minimum available balance.',
						'woocommerce-payments'
					) }
				</p>
			</div>
		</div>
		<h3>{ __( 'Payout bank account', 'woocommerce-payments' ) }</h3>
		<p>
			{ __(
				'Manage and update your deposit account information to receive payments and payouts.',
				'woocommerce-payments'
			) }
		</p>
	</div>
);

const FraudProtectionLevelField = ( {
	value,
}: SettingsFieldComponentProps ) => {
	const isAdvanced = value === 'advanced';
	const fraudProtectionUrl =
		'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&wcpay-settings-tab=fraud-protection';

	return (
		<div className="woopayments-modern-settings-card">
			<h3>
				{ __( 'Set your payment risk level', 'woocommerce-payments' ) }
			</h3>
			<div className="woopayments-modern-settings-risk-row">
				<input type="radio" checked={ ! isAdvanced } readOnly />
				<div>
					<strong>{ __( 'Basic', 'woocommerce-payments' ) }</strong>
					<p>
						{ __(
							'Provides the base level of platform protection.',
							'woocommerce-payments'
						) }
					</p>
				</div>
			</div>
			<div className="woopayments-modern-settings-risk-row">
				<input type="radio" checked={ isAdvanced } readOnly />
				<div>
					<strong>
						{ __( 'Advanced', 'woocommerce-payments' ) }
					</strong>
					<p>
						{ __(
							'Allows you to fine-tune the level of filtering based on your business needs.',
							'woocommerce-payments'
						) }
					</p>
				</div>
				<Button
					variant="secondary"
					href={ fraudProtectionUrl }
					className="woopayments-modern-settings-risk-row__action"
				>
					{ __( 'Configure', 'woocommerce-payments' ) }
				</Button>
			</div>
		</div>
	);
};

const ExpressCheckoutsField = () => {
	const rows = [
		{
			id: 'woopay',
			label: __( 'WooPay', 'woocommerce-payments' ),
			description: __(
				'Boost conversion and customer loyalty by offering a single click, secure way to pay.',
				'woocommerce-payments'
			),
			badge: 'WooPay',
		},
		{
			id: 'payment_request',
			label: __( 'Apple Pay / Google Pay', 'woocommerce-payments' ),
			description: __(
				'Offer customers a fast, secure checkout experience with Apple Pay and Google Pay.',
				'woocommerce-payments'
			),
			badge: 'Pay',
		},
		{
			id: 'link',
			label: __( 'Link by Stripe', 'woocommerce-payments' ),
			description: __(
				'Link autofills your customers’ payment and shipping details to deliver an easy checkout experience.',
				'woocommerce-payments'
			),
			badge: 'link',
		},
	];

	return (
		<div className="woopayments-modern-settings-card woopayments-modern-settings-payment-methods">
			{ rows.map( ( row ) => (
				<div
					key={ row.id }
					className="woopayments-modern-settings-payment-method"
				>
					<CheckboxControl
						className="woopayments-modern-settings-payment-method__checkbox"
						label=""
						checked
						onChange={ () => undefined }
						__nextHasNoMarginBottom
					/>
					<div className="woopayments-modern-settings-payment-method__icon woopayments-modern-settings-payment-method__icon--text">
						<span>{ row.badge }</span>
					</div>
					<div className="woopayments-modern-settings-payment-method__body">
						<div className="woopayments-modern-settings-payment-method__title">
							{ row.label }
						</div>
						<div className="woopayments-modern-settings-payment-method__description">
							{ row.description }
						</div>
					</div>
					<Button
						variant="secondary"
						href={ `admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&method=${ row.id }` }
					>
						{ __( 'Customize', 'woocommerce-payments' ) }
					</Button>
				</div>
			) ) }
		</div>
	);
};

const parseRulesetValue = ( value: SettingsFieldValue ) => {
	if ( typeof value !== 'string' ) {
		return Array.isArray( value ) ? value : [];
	}

	try {
		const parsedValue = JSON.parse( value );
		return Array.isArray( parsedValue ) ? parsedValue : [];
	} catch ( error ) {
		return [];
	}
};

const hasEnabledFraudRule = ( settings: ProtectionSettingsUI ) =>
	Object.values( settings ).some( ( setting ) => setting.enabled );

const AdvancedFraudProtectionField = ( {
	values,
	onFieldChange,
}: SettingsFieldComponentProps ) => {
	const initialRuleset = useMemo(
		() => parseRulesetValue( values.advanced_fraud_protection_settings ),
		[ values.advanced_fraud_protection_settings ]
	);
	const [ protectionSettingsUI, setProtectionSettingsUIState ] =
		useState< ProtectionSettingsUI >( () => readRuleset( initialRuleset ) );

	const updatePersistedValues = ( nextSettings: ProtectionSettingsUI ) => {
		const nextRuleset = writeRuleset( nextSettings );
		const nextProtectionLevel = hasEnabledFraudRule( nextSettings )
			? ProtectionLevel.ADVANCED
			: ProtectionLevel.BASIC;

		onFieldChange(
			'advanced_fraud_protection_settings',
			JSON.stringify( nextRuleset )
		);
		onFieldChange( 'current_protection_level', nextProtectionLevel );
	};

	const setProtectionSettingsUI = (
		update:
			| ProtectionSettingsUI
			| ( ( settings: ProtectionSettingsUI ) => ProtectionSettingsUI )
	) => {
		setProtectionSettingsUIState( ( currentSettings ) => {
			const nextSettings =
				typeof update === 'function'
					? update( currentSettings )
					: update;

			updatePersistedValues( nextSettings );

			return nextSettings;
		} );
	};

	return (
		<FraudPreventionSettingsContext.Provider
			value={ {
				protectionSettingsUI,
				setProtectionSettingsUI,
				setIsDirty: () => undefined,
			} }
		>
			<div className="woopayments-modern-settings-fraud-cards">
				<AVSMismatchRuleCard />
				<InternationalIPAddressRuleCard />
				<IPAddressMismatchRuleCard />
				<AddressMismatchRuleCard />
				<PurchasePriceThresholdRuleCard />
				<OrderItemsThresholdRuleCard />
				<CVCVerificationRuleCard />
			</div>
		</FraudPreventionSettingsContext.Provider>
	);
};

window.wcModernSettings?.registerSettingsExtension?.( {
	scope: {
		page: 'checkout',
		section: 'woocommerce_payments',
	},
	components: {
		'woopayments/payment-methods': PaymentMethodsField,
		'woopayments/payout-schedule': PayoutScheduleField,
		'woopayments/fraud-protection-level': FraudProtectionLevelField,
		'woopayments/express-checkouts': ExpressCheckoutsField,
		'woopayments/advanced-fraud-protection': AdvancedFraudProtectionField,
	},
} );
