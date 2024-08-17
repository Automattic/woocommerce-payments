/** @format */
/**
 * External dependencies
 */
import React, { useMemo } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
	CheckboxControl,
	SelectControl,
	RadioControl,
	RangeControl,
} from '@wordpress/components';
import { Elements } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { useContext } from '@wordpress/element';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import PaymentRequestButtonPreview from './payment-request-button-preview';
import interpolateComponents from '@automattic/interpolate-components';
import { getPaymentRequestData } from 'utils/express-checkout';
import WCPaySettingsContext from '../wcpay-settings-context';
import InlineNotice from 'wcpay/components/inline-notice';
import {
	usePaymentRequestButtonType,
	usePaymentRequestButtonSize,
	usePaymentRequestButtonTheme,
	usePaymentRequestButtonBorderRadius,
	usePaymentRequestEnabledSettings,
	useWooPayEnabledSettings,
	useWooPayGlobalThemeSupportEnabledSettings,
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

const makeButtonThemeText = ( string ) =>
	interpolateComponents( {
		mixedString: string,
		components: {
			br: <br />,
			helpText: (
				<span className="payment-method-settings__option-help-text" />
			),
		},
	} );

const buttonThemeOptions = [
	{
		label: makeButtonThemeText(
			__(
				'Dark {{br/}}{{helpText}}Recommended for white or light-colored backgrounds with high contrast.{{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'dark',
	},
	{
		label: makeButtonThemeText(
			__(
				'Light {{br/}}{{helpText}}Recommended for dark or colored backgrounds with high contrast.{{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'light',
	},
	{
		label: makeButtonThemeText(
			__(
				'Outline {{br/}}{{helpText}}Recommended for white or light-colored backgrounds with insufficient contrast.{{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'light-outline',
	},
];

const GeneralPaymentRequestButtonSettings = ( { type } ) => {
	const [ buttonType, setButtonType ] = usePaymentRequestButtonType();
	const [ size, setSize ] = usePaymentRequestButtonSize();
	const [ theme, setTheme ] = usePaymentRequestButtonTheme();
	const [ radius, setRadius ] = usePaymentRequestButtonBorderRadius();
	const [ isWooPayEnabled ] = useWooPayEnabledSettings();
	const [ isPaymentRequestEnabled ] = usePaymentRequestEnabledSettings();
	const {
		featureFlags: {
			woopay: isWooPayFeatureFlagEnabled,
			isStripeEceEnabled: isEceEnabled,
		},
	} = useContext( WCPaySettingsContext );

	const stripePromise = useMemo( () => {
		const stripeSettings = getPaymentRequestData( 'stripe' );
		return loadStripe( stripeSettings.publishableKey, {
			stripeAccount: stripeSettings.accountId,
			locale: stripeSettings.locale,
		} );
	}, [] );

	const otherButtons =
		type === 'woopay'
			? __( 'Apple Pay / Google Pay buttons', 'woocommerce-payments' )
			: __( 'WooPay button', 'woocommerce-payments' );

	const showWarning =
		isWooPayEnabled &&
		isPaymentRequestEnabled &&
		isWooPayFeatureFlagEnabled;

	const [
		isWooPayGlobalThemeSupportEnabled,
		updateIsWooPayGlobalThemeSupportEnabled,
	] = useWooPayGlobalThemeSupportEnabledSettings();

	return (
		<CardBody>
			{ showWarning && (
				<InlineNotice
					status="warning"
					icon={ true }
					isDismissible={ false }
				>
					{ sprintf(
						/* translators: %s type of button to which the settings will be applied */
						__(
							'These settings will also apply to the %s on your store.',
							'woocommerce-payments'
						),
						otherButtons
					) }
				</InlineNotice>
			) }
			<h4>{ __( 'Call to action', 'woocommerce-payments' ) }</h4>
			<SelectControl
				className="payment-method-settings__cta-selection"
				label={ __( 'Call to action', 'woocommerce-payments' ) }
				help={ __(
					'Select a button label that fits best wit the flow of purchase or payment experience on your store.',
					'woocommerce-payments'
				) }
				hideLabelFromVision
				value={ buttonType }
				options={ buttonActionOptions }
				onChange={ setButtonType }
			/>
			<h4>{ __( 'Button size', 'woocommerce-payments' ) }</h4>
			<RadioControl
				selected={ size }
				options={ buttonSizeOptions }
				onChange={ setSize }
			/>
			<h4>{ __( 'Theme', 'woocommerce-payments' ) }</h4>
			<RadioControl
				selected={ theme }
				options={ buttonThemeOptions }
				onChange={ setTheme }
			/>
			{ isEceEnabled && (
				<>
					<h4>{ __( 'Border radius', 'woocommerce-payments' ) }</h4>
					<div className="payment-method-settings__border-radius">
						<NumberControl
							label={ __(
								/* translators: Label for a number input, hidden from view. Intended for accessibility. */
								'Border radius, number input',
								'woocommerce-payments'
							) }
							hideLabelFromVision
							isPressEnterToChange={ true }
							value={ radius }
							max={ 30 }
							min={ 0 }
							hideHTMLArrows
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
						/>
					</div>
					<p className="payment-method-settings__option-help-text">
						{ __(
							'Controls the corner roundness of express payment buttons.',
							'woocommerce-payments'
						) }
					</p>
				</>
			) }
			{ wcpaySettings.isWooPayGlobalThemeSupportEligible &&
				type === 'woopay' && (
					<>
						<h4>
							{ __(
								'WooPay Global Theme Support',
								'woocommerce-payments'
							) }
						</h4>
						<div className="test">
							<CheckboxControl
								disabled={ ! isWooPayEnabled }
								checked={ isWooPayGlobalThemeSupportEnabled }
								onChange={
									updateIsWooPayGlobalThemeSupportEnabled
								}
								label={ __(
									'Enable WooPay Global Theme Support',
									'woocommerce-payments'
								) }
							/>
						</div>
					</>
				) }
			<h4>{ __( 'Preview', 'woocommerce-payments' ) }</h4>
			<div className="payment-method-settings__option-help-text">
				{ __(
					'See the preview of enabled express payment buttons.',
					'woocommerce-payments'
				) }
			</div>
			<Elements stripe={ stripePromise }>
				<PaymentRequestButtonPreview />
			</Elements>
		</CardBody>
	);
};

export default GeneralPaymentRequestButtonSettings;
