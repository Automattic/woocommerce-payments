/**
 * External dependencies
 */
import React from 'react';
import {
	BaseControl,
	Button,
	CheckboxControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { RawHTML } from '@wordpress/element';

/**
 * Internal dependencies
 */
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
		disabled?: boolean;
	};
	value?: SettingsFieldValue;
	onChange?: ( value: SettingsFieldValue ) => void;
	values?: Record< string, SettingsFieldValue >;
	initialValues?: Record< string, SettingsFieldValue >;
	setValue?: ( fieldId: string, value: SettingsFieldValue ) => void;
	setValues?: ( values: Record< string, SettingsFieldValue > ) => void;
};

type ModernSettingsExtensionConfig = {
	scope: {
		page: string;
		section?: string;
	};
	components?: Record<
		string,
		( props: SettingsFieldComponentProps ) => JSX.Element | null
	>;
};

declare global {
	interface Window {
		wcModernSettings?: {
			registerSettingsExtension?: (
				config: ModernSettingsExtensionConfig
			) => void;
		};
	}
}

const getFieldValue = (
	props: SettingsFieldComponentProps,
	fieldId = props.field.id
): SettingsFieldValue => {
	if (
		props.values &&
		Object.prototype.hasOwnProperty.call( props.values, fieldId )
	) {
		return props.values[ fieldId ];
	}

	return props.value ?? null;
};

const setFieldValue = (
	props: SettingsFieldComponentProps,
	fieldId: string,
	value: SettingsFieldValue
) => {
	if ( props.setValue ) {
		props.setValue( fieldId, value );
		return;
	}

	if ( fieldId === props.field.id ) {
		props.onChange?.( value );
	}
};

const PaymentMethodsField = ( props: SettingsFieldComponentProps ) => {
	const { field } = props;
	const sourceFieldId = 'upe_enabled_payment_method_ids';
	const sourceValue = getFieldValue( props, sourceFieldId );
	const selectedValues = Array.isArray( sourceValue ) ? sourceValue : [];
	const category =
		field.id === 'upe_enabled_payment_method_ids_bnpl'
			? 'buy-now-pay-later'
			: 'standard';
	const options = ( field.options || [] ).filter(
		( option ) => ( option.category || 'standard' ) === category
	);

	const updateSelectedValues = ( optionValue: string, checked: boolean ) => {
		if ( checked ) {
			setFieldValue(
				props,
				sourceFieldId,
				Array.from( new Set( [ ...selectedValues, optionValue ] ) )
			);
			return;
		}

		setFieldValue(
			props,
			sourceFieldId,
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

const FraudProtectionLevelField = ( props: SettingsFieldComponentProps ) => {
	const isAdvanced = getFieldValue( props ) === 'advanced';
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

const AdvancedFraudProtectionField = ( props: SettingsFieldComponentProps ) => {
	const value = getFieldValue( props );
	const parsedRules = ( () => {
		try {
			return typeof value === 'string' ? JSON.parse( value ) : [];
		} catch ( error ) {
			return [];
		}
	} )();
	const enabledRules = Array.isArray( parsedRules )
		? parsedRules
				.filter( ( rule ) => rule?.key )
				.map( ( rule ) => rule.key )
		: [];
	const ruleCards = [
		{
			id: 'avs_verification',
			title: __( 'AVS Mismatch', 'woocommerce-payments' ),
			label: __( 'Enable AVS Mismatch filter', 'woocommerce-payments' ),
			description: __(
				'This filter compares the street number and the post code submitted by the customer against the data on file with the card issuer. When enabled the payment will be blocked.',
				'woocommerce-payments'
			),
			help: __(
				'Buyers who can provide the street number and post code on file with the issuing bank are more likely to be the actual account holder.',
				'woocommerce-payments'
			),
		},
		{
			id: 'international_ip_address',
			title: __( 'International IP Address', 'woocommerce-payments' ),
			label: '',
			description: __(
				'This filter is disabled because you’re currently selling to all countries.',
				'woocommerce-payments'
			),
			help: __(
				'You should be especially wary when a customer has an international IP address but uses domestic billing and shipping information.',
				'woocommerce-payments'
			),
			notice: true,
		},
		{
			id: 'ip_address_mismatch',
			title: __( 'IP Address Mismatch', 'woocommerce-payments' ),
			label: __(
				'Enable IP Address Mismatch filter',
				'woocommerce-payments'
			),
			description: __(
				'This filter screens for customer’s IP address to see if it is in a different country than indicated in their billing address.',
				'woocommerce-payments'
			),
			help: __(
				'Fraudulent transactions often use fake addresses to place orders. If the IP address seems to be in one country, but the billing address is in another, that could signal potential fraud.',
				'woocommerce-payments'
			),
		},
		{
			id: 'address_mismatch',
			title: __( 'Address Mismatch', 'woocommerce-payments' ),
			label: __(
				'Enable Address Mismatch filter',
				'woocommerce-payments'
			),
			description: __(
				'This filter screens for differences between the shipping information and the billing information.',
				'woocommerce-payments'
			),
			help: __(
				'There are legitimate reasons for a billing/shipping mismatch, but a mismatch could also indicate that someone is using a stolen identity.',
				'woocommerce-payments'
			),
		},
	];

	return (
		<div className="woopayments-modern-settings-fraud-cards">
			{ ruleCards.map( ( rule ) => (
				<div
					key={ rule.id }
					className="woopayments-modern-settings-card woopayments-modern-settings-fraud-card"
				>
					<h3>{ rule.title }</h3>
					{ rule.notice ? (
						<div className="woopayments-modern-settings-notice">
							{ rule.description }
						</div>
					) : (
						<ToggleControl
							label={ rule.label }
							checked={ enabledRules.includes( rule.id ) }
							onChange={ () => undefined }
							__nextHasNoMarginBottom
						/>
					) }
					{ ! rule.notice ? <p>{ rule.description }</p> : null }
					<h4>
						{ __(
							'How does this filter protect me?',
							'woocommerce-payments'
						) }
					</h4>
					<p>{ rule.help }</p>
				</div>
			) ) }
		</div>
	);
};

const modernSettingsWindow = window as Window & {
	wc?: {
		modernSettingsSdk?: {
			registerSettingsExtension?: (
				config: ModernSettingsExtensionConfig
			) => void;
		};
	};
};

const registerSettingsExtension =
	modernSettingsWindow.wc?.modernSettingsSdk?.registerSettingsExtension ||
	window.wcModernSettings?.registerSettingsExtension;

registerSettingsExtension?.( {
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
