/** @format */
/**
 * External dependencies
 */
import React, { useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { Elements } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';

/**
 * Internal dependencies
 */
import {
	SelectControl,
	RadioControl,
	RangeControl,
	BaseControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import CardBody from '../card-body';
import PaymentRequestButtonPreview from './payment-request-button-preview';
import interpolateComponents from '@automattic/interpolate-components';
import { getExpressCheckoutConfig } from 'utils/express-checkout';
import ExpressCheckoutSettingsNotices from './express-checkout-settings-notices';
import {
	usePaymentRequestButtonType,
	usePaymentRequestButtonSize,
	usePaymentRequestButtonTheme,
	usePaymentRequestButtonBorderRadius,
} from 'wcpay/data';

const makeButtonSizeText = ( string ) =>
	interpolateComponents( {
		mixedString: string,
		components: {
			helpText: (
				<span className="payment-method-settings__option-muted-text" />
			),
		},
	} );

const buttonSizeOptions = [
	{
		label: makeButtonSizeText(
			__(
				'Small {{helpText}}(40 px){{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'small',
	},
	{
		label: makeButtonSizeText(
			__(
				'Medium {{helpText}}(48 px){{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'medium',
	},
	{
		label: makeButtonSizeText(
			__(
				'Large {{helpText}}(55 px){{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'large',
	},
];

const buttonActionOptions = [
	{
		label: __( 'Only icon', 'woocommerce-payments' ),
		value: 'default',
	},
	{
		label: __( 'Buy with', 'woocommerce-payments' ),
		value: 'buy',
	},
	{
		label: __( 'Donate with', 'woocommerce-payments' ),
		value: 'donate',
	},
	{
		label: __( 'Book with', 'woocommerce-payments' ),
		value: 'book',
	},
];

const buttonThemeOptions = [
	{
		label: __( 'Dark', 'woocommerce-payments' ),
		value: 'dark',
		description: __(
			'Recommended for white or light-colored backgrounds with high contrast.',
			'woocommerce-payments'
		),
	},
	{
		label: __( 'Light', 'woocommerce-payments' ),
		value: 'light',
		description: __(
			'Recommended for dark or colored backgrounds with high contrast.',
			'woocommerce-payments'
		),
	},
	{
		label: __( 'Outline', 'woocommerce-payments' ),
		value: 'light-outline',
		description: __(
			'Recommended for white or light-colored backgrounds with insufficient contrast.',
			'woocommerce-payments'
		),
	},
];

const GeneralPaymentRequestButtonSettings = ( { type } ) => {
	const [ buttonType, setButtonType ] = usePaymentRequestButtonType();
	const [ size, setSize ] = usePaymentRequestButtonSize();
	const [ theme, setTheme ] = usePaymentRequestButtonTheme();
	const [ radius, setRadius ] = usePaymentRequestButtonBorderRadius();

	const stripePromise = useMemo( () => {
		const stripeSettings = getExpressCheckoutConfig( 'stripe' );
		return loadStripe( stripeSettings.publishableKey, {
			stripeAccount: stripeSettings.accountId,
			locale: stripeSettings.locale,
		} );
	}, [] );

	return (
		<CardBody className="wcpay-card-body">
			<ExpressCheckoutSettingsNotices currentMethod={ type } />
			<SelectControl
				className="payment-method-settings__cta-selection"
				label={ __( 'Call to action', 'woocommerce-payments' ) }
				help={ __(
					'Select a button label that fits best with the flow of purchase or payment experience on your store.',
					'woocommerce-payments'
				) }
				value={ buttonType }
				options={ buttonActionOptions }
				onChange={ setButtonType }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			<RadioControl
				label={ __( 'Button size', 'woocommerce-payments' ) }
				selected={ size }
				options={ buttonSizeOptions }
				onChange={ setSize }
			/>
			<RadioControl
				label={ __( 'Theme', 'woocommerce-payments' ) }
				selected={ theme }
				options={ buttonThemeOptions }
				onChange={ setTheme }
			/>
			<BaseControl
				id="wcpay-payment-request-settings-border-radius"
				label={ __( 'Border radius', 'woocommerce-payments' ) }
				help={ __(
					'Controls the corner roundness of express payment buttons.',
					'woocommerce-payments'
				) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			>
				<div className="payment-method-settings__border-radius">
					<NumberControl
						label={ __(
							/* translators: Label for a number input, hidden from view. Intended for accessibility. */
							'Border radius, number input',
							'woocommerce-payments'
						) }
						id="wcpay-payment-request-settings-border-radius"
						hideLabelFromVision
						isPressEnterToChange={ true }
						value={ radius }
						max={ 30 }
						min={ 0 }
						spinControls="none"
						onChange={ ( value ) => {
							if ( typeof value === 'string' ) {
								setRadius( parseInt( value, 10 ) );
							} else {
								setRadius( value );
							}
						} }
						suffix={
							<div className="payment-method-settings__border-radius__number-control__suffix">
								px
							</div>
						}
						__next40pxDefaultSize
					/>
					<RangeControl
						label={ __(
							/* translators: Label for an input slider, hidden from view. Intended for accessibility. */
							'Border radius, slider',
							'woocommerce-payments'
						) }
						hideLabelFromVision
						className="payment-method-settings__border-radius__slider"
						value={ radius }
						max={ 30 }
						min={ 0 }
						withInputField={ false }
						onChange={ setRadius }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</div>
			</BaseControl>
			{ /* eslint-disable-next-line @wordpress/no-base-control-with-label-without-id */ }
			<BaseControl
				label={ __( 'Preview', 'woocommerce-payments' ) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			>
				<div className="payment-method-settings__option-help-text">
					{ __(
						'See the preview of enabled express payment buttons.',
						'woocommerce-payments'
					) }
				</div>
				<Elements stripe={ stripePromise }>
					<PaymentRequestButtonPreview />
				</Elements>
			</BaseControl>
		</CardBody>
	);
};

export default GeneralPaymentRequestButtonSettings;
