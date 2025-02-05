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

			let shippingRates;
			if ( shippingAddressRequired ) {
				const hasValidRates =
					shippingData?.shippingRates[ 0 ]?.shipping_rates?.length >
					0;

				if ( hasValidRates ) {
					shippingRates = shippingData.shippingRates[ 0 ].shipping_rates.map(
						( rate ) => {
							return {
								id: rate.rate_id,
								amount: parseInt( rate.price, 10 ),
								displayName: rate.name,
							};
						}
					);
				} else {
					shippingRates = [
						{
							id: 'pending',
							displayName: __(
								'Pending',
								'woocommerce-payments'
							),
							amount: 0,
						},
					];
				}
			}

			const options = {
				lineItems: normalizeLineItems( billing?.cartTotalItems ),
				emailRequired: true,
				shippingAddressRequired,
				phoneNumberRequired:
					getExpressCheckoutData( 'checkout' )?.needs_payer_phone ??
					false,
				shippingRates,
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
			{
				...event,
				order_comments: wp?.data
					?.select( 'wc/store/checkout' )
					?.getOrderNotes(),
			}
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
