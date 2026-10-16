/**
 * External dependencies
 */
import { useMemo } from 'react';
import { Elements } from '@stripe/react-stripe-js';
import { useSelect } from '@wordpress/data';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import ExpressCheckoutComponent from './express-checkout-component';
import {
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutData,
	filterCartMethodsByLocation,
} from '../../utils';
import { rememberElementCurrency } from '../../utils/element-currency-cache';
import { transformPrice } from '../../transformers/wc-to-stripe';
import { resolveSetupFutureUsage } from '../../utils/subscriptions';
import '../express-checkout-element.scss';
import { WC_STORE_CART } from 'wcpay/checkout/constants';

const ExpressCheckoutContainer = ( props ) => {
	const { api, billing, buttonAttributes } = props;

	const stripePromise = useMemo( () => {
		return api.loadStripeForExpressCheckout();
	}, [ api ] );

	const useConfirmationToken =
		getExpressCheckoutData( 'flags' )?.isEceUsingConfirmationTokens ?? true;
	const isManualCaptureEnabled =
		getExpressCheckoutData( 'is_manual_capture' ) ?? false;
	const cartData = useSelect(
		( selectCart ) => selectCart( WC_STORE_CART )?.getCartData(),
		[]
	);

	const enabledMethodsFromCart = useSelect(
		( s ) =>
			s( WC_STORE_CART )?.getCartData()?.extensions?.wcpay
				?.express_checkout_methods,
		[]
	);
	const enabledMethods = useMemo(
		() =>
			Array.isArray( enabledMethodsFromCart )
				? filterCartMethodsByLocation( enabledMethodsFromCart )
				: getExpressCheckoutData( 'enabled_methods' ),
		[ enabledMethodsFromCart ]
	);
	// Building the payment method types array to send to the server,
	// to ensure PaymentIntent uses matching types.
	const paymentMethodTypes = useMemo( () => {
		const methods = enabledMethods || [];

		return [
			methods.includes( 'payment_request' ) && 'card',
			methods.includes( 'amazon_pay' ) && 'amazon_pay',
		].filter( Boolean );
	}, [ enabledMethods ] );

	const elementCurrency = billing.currency.code.toLowerCase();

	// Blocks passes a new `buttonAttributes` object on every render, so key on
	// the border radius to keep `options` from rebuilding.
	const hasButtonAttributes = typeof buttonAttributes !== 'undefined';
	const buttonBorderRadius = buttonAttributes?.borderRadius;
	const appearance = useMemo(
		() =>
			getExpressCheckoutButtonAppearance(
				hasButtonAttributes
					? { borderRadius: buttonBorderRadius }
					: undefined
			),
		[ hasButtonAttributes, buttonBorderRadius ]
	);

	// Outside the memo: callbacks may read state its deps don't track.
	const amount = applyFilters(
		'wcpay.express-checkout.total-amount',
		transformPrice( billing.cartTotal.value, {
			currency_minor_unit: billing.currency.minorUnit ?? 0,
		} ),
		cartData
	);

	const options = useMemo(
		() => ( {
			mode: 'payment',
			...( useConfirmationToken
				? { paymentMethodTypes }
				: { paymentMethodCreation: 'manual' } ),
			...( useConfirmationToken && isManualCaptureEnabled
				? { captureMethod: 'manual' }
				: {} ),
			...( useConfirmationToken
				? { setupFutureUsage: resolveSetupFutureUsage( cartData ) }
				: {} ),
			amount,
			currency: rememberElementCurrency( elementCurrency ),
			appearance,
			locale: getExpressCheckoutData( 'stripe' )?.locale ?? 'en',
		} ),
		[
			useConfirmationToken,
			isManualCaptureEnabled,
			paymentMethodTypes,
			cartData,
			amount,
			elementCurrency,
			appearance,
		]
	);

	return (
		<div style={ { minHeight: '40px' } }>
			<Elements stripe={ stripePromise } options={ options }>
				<ExpressCheckoutComponent
					{ ...props }
					paymentMethodTypes={ paymentMethodTypes }
				/>
			</Elements>
		</div>
	);
};

export default ExpressCheckoutContainer;
