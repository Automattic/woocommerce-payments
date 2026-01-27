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
import { transformPrice } from '../../transformers/wc-to-stripe';
import { SHIPPING_RATES_UPPER_LIMIT_COUNT } from 'wcpay/express-checkout/constants';

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

			let shippingRates;
			if ( shippingAddressRequired ) {
				const hasValidRates =
					shippingData?.shippingRates[ 0 ]?.shipping_rates?.length >
					0;

				if ( hasValidRates ) {
					shippingRates = shippingData.shippingRates[ 0 ].shipping_rates
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

			const lineItems = normalizeLineItems( billing.cartTotalItems ).map(
				( item ) => ( {
					...item,
					// ensuring that the amount is transformed to the correct format expected by Stripe.
					amount: transformPrice( item.amount, {
						currency_minor_unit: billing.currency.minorUnit ?? 0,
					} ),
				} )
			);
			const lineItemsTotals = lineItems.reduce(
				( acc, lineItem ) => acc + lineItem.amount,
				0
			);

			const cartTotals = transformPrice( billing.cartTotal.value, {
				currency_minor_unit: billing.currency.minorUnit ?? 0,
			} );

			const options = {
				business: {
					name: getExpressCheckoutData( 'store_name' ),
				},
				// if the transformed cart total is less than the total of `lineItems`, Stripe throws an error
				// it can sometimes happen that the total is _slightly_ less, due to rounding errors on individual items/taxes/shipping
				// (or with the `woocommerce_tax_round_at_subtotal` setting).
				// if that happens, let's just not return any of the line items.
				// This way, just the total amount will be displayed to the customer.
				lineItems: cartTotals < lineItemsTotals ? [] : lineItems,
				emailRequired: true,
				shippingAddressRequired,
				phoneNumberRequired:
					getExpressCheckoutData( 'checkout' )?.needs_payer_phone ??
					false,
				shippingRates,
				allowedShippingCountries: getExpressCheckoutData( 'checkout' )
					.allowed_shipping_countries,
			};

			// console.log( '### options', options );

			// Click event from WC Blocks.
			onClick();
			// Global click event handler from WooPayments to ECE.
			onClickHandler( event );
			event.resolve( options );
		},
		[
			onClick,
			billing.cartTotalItems,
			billing.cartTotal.value,
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
