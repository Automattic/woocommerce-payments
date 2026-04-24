/**
 * External dependencies
 */
import React from 'react';
import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import PaymentMethodItem from 'wcpay/components/payment-method-item';
import './style.scss';

const PAYMENT_METHODS_FIELD_ID =
	'woocommerce_woocommerce_payments_upe_enabled_payment_method_ids';
const CARD_PAYMENT_METHOD_ID = 'card';

const parseOptions = ( options ) => {
	if ( Array.isArray( options ) ) {
		return options.map( ( option ) => ( {
			label: String( option.label || option.value || '' ),
			value: String( option.value || '' ),
		} ) );
	}

	return Object.keys( options || {} ).map( ( value ) => ( {
		label: String( options[ value ] || '' ),
		value: String( value ),
	} ) );
};

const getPaymentMethodDefinition = ( id ) =>
	window.wooPaymentsPaymentMethodDefinitions?.[ id ] || {};

const getSelectedValues = ( props, fieldId ) => {
	const rawValue = props.data?.[ fieldId ];

	if ( Array.isArray( rawValue ) ) {
		return rawValue.map( String );
	}

	return rawValue ? [ String( rawValue ) ] : [];
};

const updateSelectedValues = (
	props,
	fieldId,
	selectedValues,
	value,
	checked
) => {
	const nextValues = checked
		? [ ...selectedValues, value ]
		: selectedValues.filter( ( selectedValue ) => selectedValue !== value );

	props.onChange( {
		[ fieldId ]: nextValues,
	} );
};

const WCPayCheckboxList = ( { baseField, options, props } ) => {
	const fieldId = props.field?.id || baseField.id;
	const selectedValues = getSelectedValues( props, fieldId );

	return (
		<fieldset className="wcpay-modern-settings-multiselect">
			<legend
				className={
					props.hideLabelFromVision
						? 'screen-reader-text'
						: 'components-base-control__label'
				}
			>
				{ baseField.label }
			</legend>
			<div className="wcpay-modern-settings-multiselect__checkboxes">
				{ options.map( ( option ) => (
					<CheckboxControl
						key={ option.value }
						label={ option.label }
						checked={ selectedValues.includes( option.value ) }
						onChange={ ( checked ) =>
							updateSelectedValues(
								props,
								fieldId,
								selectedValues,
								option.value,
								checked
							)
						}
						__nextHasNoMarginBottom
					/>
				) ) }
			</div>
			{ baseField.description && (
				<p className="components-base-control__help">
					{ baseField.description }
				</p>
			) }
		</fieldset>
	);
};

const WCPayPaymentMethodsList = ( { baseField, options, props } ) => {
	const fieldId = props.field?.id || baseField.id;
	const selectedValues = getSelectedValues( props, fieldId );

	return (
		<div className="wcpay-modern-settings-multiselect wcpay-modern-settings-payment-methods">
			<div
				className={
					props.hideLabelFromVision
						? 'screen-reader-text'
						: 'components-base-control__label'
				}
			>
				{ baseField.label }
			</div>
			<ul className="payment-methods-list payment-methods__available-methods">
				{ options.map( ( option ) => {
					const definition = getPaymentMethodDefinition(
						option.value
					);
					const label = definition.label || option.label;
					const description = definition.description || '';
					const isCard = option.value === CARD_PAYMENT_METHOD_ID;
					const Icon = definition.settings_icon_url
						? () => (
								<img
									src={ definition.settings_icon_url }
									alt={ label }
									className="payment-method__icon"
								/>
						  )
						: null;

					return (
						<PaymentMethodItem
							key={ option.value }
							className="payment-method__list-item"
						>
							<PaymentMethodItem.Checkbox
								label={ label }
								checked={ selectedValues.includes(
									option.value
								) }
								disabled={
									isCard &&
									selectedValues.includes( option.value )
								}
								onChange={ ( checked ) =>
									updateSelectedValues(
										props,
										fieldId,
										selectedValues,
										option.value,
										checked
									)
								}
							/>
							<PaymentMethodItem.Body>
								<PaymentMethodItem.Subgroup
									Icon={ Icon }
									label={
										<>
											{ label }
											{ isCard && (
												<span className="payment-method__required-label">
													{ ` (${ __(
														'Required',
														'woocommerce-payments'
													) })` }
												</span>
											) }
										</>
									}
								>
									{ description }
								</PaymentMethodItem.Subgroup>
							</PaymentMethodItem.Body>
						</PaymentMethodItem>
					);
				} ) }
			</ul>
			{ baseField.description && (
				<p className="components-base-control__help">
					{ baseField.description }
				</p>
			) }
		</div>
	);
};

const registerMultiselectTransformer = ( registerFieldTypeTransformer ) => {
	if (
		typeof registerFieldTypeTransformer !== 'function' ||
		registerFieldTypeTransformer.__wcpayMultiselectRegistered
	) {
		return;
	}

	registerFieldTypeTransformer.__wcpayMultiselectRegistered = true;
	registerFieldTypeTransformer( 'multiselect', ( setting, baseField ) => {
		const options = parseOptions( setting.options );
		const optionValues = options.map( ( option ) => option.value );
		const Edit =
			setting.id === PAYMENT_METHODS_FIELD_ID
				? ( props ) => (
						<WCPayPaymentMethodsList
							baseField={ baseField }
							options={ options }
							props={ props }
						/>
				  )
				: ( props ) => (
						<WCPayCheckboxList
							baseField={ baseField }
							options={ options }
							props={ props }
						/>
				  );

		return {
			...baseField,
			type: 'array',
			elements: options,
			Edit,
			isValid: ( value ) =>
				Array.isArray( value ) &&
				value.every( ( item ) => optionValues.includes( item ) ),
		};
	} );
};

window.wcReactSettings = window.wcReactSettings || {};

if (
	typeof window.wcReactSettings.registerFieldTypeTransformer === 'function'
) {
	registerMultiselectTransformer(
		window.wcReactSettings.registerFieldTypeTransformer
	);
} else {
	let currentRegisterFieldTypeTransformer;
	Object.defineProperty(
		window.wcReactSettings,
		'registerFieldTypeTransformer',
		{
			configurable: true,
			get() {
				return currentRegisterFieldTypeTransformer;
			},
			set( nextRegisterFieldTypeTransformer ) {
				currentRegisterFieldTypeTransformer =
					nextRegisterFieldTypeTransformer;
				registerMultiselectTransformer(
					nextRegisterFieldTypeTransformer
				);
			},
		}
	);
}
