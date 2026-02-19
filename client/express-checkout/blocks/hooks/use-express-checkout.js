/**
 * External dependencies
 */
import { useCallback } from '@wordpress/element';
import { useStripe, useElements } from '@stripe/react-stripe-js';
import { select } from '@wordpress/data';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	displayLoginConfirmation,
	getExpressCheckoutButtonStyleSettings,
	getExpressCheckoutData,
} from '../../utils';
import {
	onAbortPaymentHandler,
	onCancelHandler,
	onClickHandler,
	onCompletePaymentHandler,
	onConfirmHandler,
	onReadyHandler,
} from '../../event-handlers';
import {
	transformCartDataForDisplayItems,
	transformPrice,
} from '../../transformers/wc-to-stripe';
import { SHIPPING_RATES_UPPER_LIMIT_COUNT } from 'wcpay/express-checkout/constants';
import { WC_STORE_CART } from 'wcpay/checkout/constants';

export const useExpressCheckout = ( {
	api,
	billing,
	shippingData,
	onClick,
	onClose,
	setExpressPaymentError,
	paymentMethodTypes = [],
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

			// Get cart data with extensions for subscription handling
			const cartData = select( WC_STORE_CART )?.getCartData();

			let shippingRates;
			if ( shippingAddressRequired ) {
				// Get base shipping rates from WC Blocks
				const baseShippingRates =
					shippingData?.shippingRates[ 0 ]?.shipping_rates || [];

				// Apply filter to allow modifications (e.g., for trial subscriptions
				// where shipping rates are in subscription extensions)
				const effectiveShippingRates = applyFilters(
					'wcpay.express-checkout.shipping-rates',
					baseShippingRates,
					cartData
				);

				const hasValidRates = effectiveShippingRates?.length > 0;

				if ( hasValidRates ) {
					shippingRates = effectiveShippingRates
						.map( ( rate ) => {
							return {
								id: rate.rate_id,
								amount: transformPrice(
									parseInt( rate.price, 10 ),
									{
										currency_minor_unit:
											billing.currency.minorUnit ?? 0,
									}
								),
								displayName: rate.name,
							};
						} )
						.slice( 0, SHIPPING_RATES_UPPER_LIMIT_COUNT );
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
				business: {
					name: getExpressCheckoutData( 'store_name' ),
				},
				lineItems: transformCartDataForDisplayItems( cartData ),
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
			shippingData.needsShipping,
			shippingData.shippingRates,
			billing.currency.minorUnit,
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
			},
			paymentMethodTypes
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
