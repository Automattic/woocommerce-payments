/**
 * External dependencies
 */
import { useMemo } from 'react';
import { Elements } from '@stripe/react-stripe-js';
import { select } from '@wordpress/data';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import ExpressCheckoutComponent from './express-checkout-component';
import {
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutData,
} from '../../utils';
import { transformPrice } from '../../transformers/wc-to-stripe';
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
	const hasSubscription =
		getExpressCheckoutData( 'has_subscription' ) ?? false;

	const enabledMethods = getExpressCheckoutData( 'enabled_methods' );
	// Building the payment method types array to send to the server,
	// to ensure PaymentIntent uses matching types.
	const paymentMethodTypes = useMemo( () => {
		const methods = enabledMethods || [];

		return [
			methods.includes( 'payment_request' ) && 'card',
			methods.includes( 'amazon_pay' ) && 'amazon_pay',
		].filter( Boolean );
	}, [ enabledMethods ] );

	const options = {
		mode: 'payment',
		...( useConfirmationToken
			? { paymentMethodTypes }
			: { paymentMethodCreation: 'manual' } ),
		...( useConfirmationToken && isManualCaptureEnabled
			? { captureMethod: 'manual' }
			: {} ),
		...( useConfirmationToken && hasSubscription
			? { setupFutureUsage: 'off_session' }
			: {} ),
		// Apply filter to allow modifications (e.g., for trial subscriptions with $0 initial payment)
		amount: applyFilters(
			'wcpay.express-checkout.total-amount',
			transformPrice( billing.cartTotal.value, {
				currency_minor_unit: billing.currency.minorUnit ?? 0,
			} ),
			select( WC_STORE_CART )?.getCartData()
		),
		currency: billing.currency.code.toLowerCase(),
		appearance: getExpressCheckoutButtonAppearance( buttonAttributes ),
		locale: getExpressCheckoutData( 'stripe' )?.locale ?? 'en',
	};

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
