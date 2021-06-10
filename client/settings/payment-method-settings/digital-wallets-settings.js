/** @format */
/**
 * External dependencies
 */
import { React, useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Card, RadioControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { getPaymentRequestData } from '../../payment-request/utils';
import {
	Elements,
	PaymentRequestButtonElement,
	useStripe,
} from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';

/**
 * Internal dependencies
 */
import {
	useDigitalWalletsButtonType,
	useDigitalWalletsButtonSize,
	useDigitalWalletsButtonTheme,
} from '../../data';
import CardBody from '../card-body';

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
				'Default {{helpText}}(40 px){{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'default',
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
				'Large {{helpText}}(56 px){{/helpText}}',
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
		label: __( 'Buy', 'woocommerce-payments' ),
		value: 'buy',
	},
	{
		label: __( 'Donate', 'woocommerce-payments' ),
		value: 'donate',
	},
	{
		label: __( 'Book', 'woocommerce-payments' ),
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
];

const PaymentButton = ( props ) => {
	const stripe = useStripe();
	const [ paymentRequest, setPaymentRequest ] = useState( null );

	const sizeToPx = ( size ) => {
		const sizeToPxMappings = {
			default: 40,
			medium: 48,
			large: 56,
		};
		return sizeToPxMappings[ size ] + 'px';
	};

	useEffect( () => {
		if ( ! stripe ) {
			return;
		}

		const pr = stripe.paymentRequest( {
			country: 'US',
			currency: 'usd',
			total: {
				label: 'Demo total',
				amount: 1099,
			},
			requestPayerName: true,
			requestPayerEmail: true,
		} );

		// Check the availability of the Payment Request API.
		pr.canMakePayment().then( ( result ) => {
			if ( result ) {
				setPaymentRequest( pr );
			}
		} );
	}, [ stripe ] );

	if ( paymentRequest ) {
		return (
			<PaymentRequestButtonElement
				options={ {
					paymentRequest,
					style: {
						paymentRequestButton: {
							type: props.buttonType,
							theme: props.theme,
							height: sizeToPx( props.size ),
						},
					},
				} }
			/>
		);
	}
	return 'Your browser does not support express checkout.';
};

const stripeSettings = getPaymentRequestData( 'stripe' );
const stripePromise = loadStripe( stripeSettings.publishableKey, {
	stripeAccount: stripeSettings.accountId,
	locale: stripeSettings.locale,
} );

const DigitalWalletsSettings = () => {
	const [ buttonType, setButtonType ] = useDigitalWalletsButtonType();
	const [ size, setSize ] = useDigitalWalletsButtonSize();
	const [ theme, setTheme ] = useDigitalWalletsButtonTheme();

	return (
		<Card>
			<CardBody>
				<h4>{ __( 'Call to action', 'woocommerce-payments' ) }</h4>
				<RadioControl
					className="payment-method-settings__cta-selection"
					label={ __( 'Call to action', 'woocommerce-payments' ) }
					hideLabelFromVision
					help={ __(
						'Select a button label that fits best with the flow of purchase or payment experience on your store.',
						'woocommerce-payments'
					) }
					selected={ buttonType }
					options={ buttonActionOptions }
					onChange={ setButtonType }
				/>
				<h4>{ __( 'Appearance', 'woocommerce-payments' ) }</h4>
				<RadioControl
					help={ __(
						'Note that larger buttons are more suitable for mobile use.',
						'woocommerce-payments'
					) }
					label={ __( 'Size', 'woocommerce-payments' ) }
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
				<p>{ __( 'Preview', 'woocommerce-payments' ) }</p>
				<Elements stripe={ stripePromise }>
					<PaymentButton
						buttonType={ buttonType }
						size={ size }
						theme={ theme }
					/>
				</Elements>
			</CardBody>
		</Card>
	);
};

export default DigitalWalletsSettings;
