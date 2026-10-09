/**
 * External dependencies
 */
import { useMemo, useState } from 'react';
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
	const enabledMethods = Array.isArray( enabledMethodsFromCart )
		? filterCartMethodsByLocation( enabledMethodsFromCart )
		: getExpressCheckoutData( 'enabled_methods' );
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
	const [ isAvailable, setIsAvailable ] = useState( true );

	const options = {
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
		// Apply filter to allow modifications (e.g., for trial subscriptions with $0 initial payment)
		amount: applyFilters(
			'wcpay.express-checkout.total-amount',
			transformPrice( billing.cartTotal.value, {
				currency_minor_unit: billing.currency.minorUnit ?? 0,
			} ),
			cartData
		),
		currency: rememberElementCurrency( elementCurrency ),
		appearance: getExpressCheckoutButtonAppearance( buttonAttributes ),
		locale: getExpressCheckoutData( 'stripe' )?.locale ?? 'en',
	};

	return (
		<div
			className="wcpay-ece-slot"
			style={ { minHeight: '40px' } }
			hidden={ ! isAvailable }
		>
			{ /*
			 * Stripe accepts a new currency through elements.update() but doesn't
			 * re-check which wallets support it, so start over with a fresh group.
			 */ }
			<Elements
				key={ elementCurrency }
				stripe={ stripePromise }
				options={ options }
			>
				<ExpressCheckoutComponent
					{ ...props }
					paymentMethodTypes={ paymentMethodTypes }
					onAvailabilityChange={ setIsAvailable }
				/>
			</Elements>
		</div>
	);
};

export default ExpressCheckoutContainer;
