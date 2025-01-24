/**
 * External dependencies
 */
import { useCallback } from '@wordpress/element';
import { useStripe, useElements } from '@stripe/react-stripe-js';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	displayLoginConfirmation,
	getExpressCheckoutButtonStyleSettings,
	getExpressCheckoutData,
	normalizeLineItems,
} from '../../utils';
import {
	onAbortPaymentHandler,
	onCancelHandler,
	onClickHandler,
	onCompletePaymentHandler,
	onConfirmHandler,
	onReadyHandler,
} from '../../event-handlers';

export const useExpressCheckout = ( {
	api,
	billing,
	shippingData,
	onClick,
	onClose,
	setExpressPaymentError,
} ) => {
	const stripe = useStripe();
	const elements = useElements();

	const buttonOptions = getExpressCheckoutButtonStyleSettings();

	const onCancel = () => {
		onCancelHandler();
		onClose();
	};

	const completePayment = ( redirectUrl ) => {
		onCompletePaymentHandler();
		window.location = redirectUrl;
	};

	const abortPayment = ( message ) => {
		setExpressPaymentError( message );
		onAbortPaymentHandler();
	};

	const onButtonClick = useCallback(
		( event ) => {
			// If login is required for checkout, display redirect confirmation dialog.
			if ( getExpressCheckoutData( 'login_confirmation' ) ) {
				displayLoginConfirmation( event.expressPaymentType );
				return;
			}

			const shippingAddressRequired = shippingData?.needsShipping;

			const shippingRatesMap = shippingData?.shippingRates[ 0 ]?.shipping_rates?.map(
				( rate ) => {
					return {
						id: rate.rate_id,
						amount: parseInt( rate.price, 10 ),
						displayName: rate.name,
					};
				}
			);
			const shippingOptionsWithFallback =
				// the variable could be undefined or it could just be an array without values.
				! shippingRatesMap || shippingRatesMap.length === 0
					? [
							// fallback for initialization (and initialization _only_), before an address is provided by the ECE.
							{
								id: 'pending',
								displayName: __(
									'Pending',
									'woocommerce-payments'
								),
								amount: 0,
							},
					  ]
					: shippingRatesMap;

			const options = {
				lineItems: normalizeLineItems( billing?.cartTotalItems ),
				emailRequired: true,
				shippingAddressRequired,
				phoneNumberRequired:
					getExpressCheckoutData( 'checkout' )?.needs_payer_phone ??
					false,
				shippingRates: shippingAddressRequired
					? shippingOptionsWithFallback
					: undefined,
				allowedShippingCountries: getExpressCheckoutData( 'checkout' )
					.allowed_shipping_countries,
			};

			// Click event from WC Blocks.
			onClick();
			// Global click event handler from WooPayments to ECE.
			onClickHandler( event );
			event.resolve( options );
		},
		[
			onClick,
			billing.cartTotalItems,
			shippingData.needsShipping,
			shippingData.shippingRates,
		]
	);

	const onConfirm = async ( event ) => {
		onConfirmHandler(
			api,
			stripe,
			elements,
			completePayment,
			abortPayment,
			event
		);
	};

	return {
		buttonOptions,
		onButtonClick,
		onConfirm,
		onReady: onReadyHandler,
		onCancel,
		elements,
	};
};
