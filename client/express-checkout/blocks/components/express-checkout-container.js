/**
 * External dependencies
 */
import { useMemo } from 'react';
import { Elements } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import ExpressCheckoutComponent from './express-checkout-component';
import {
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutData,
	getSetupFutureUsage,
} from '../../utils';
import { transformPrice } from '../../transformers/wc-to-stripe';
import '../express-checkout-element.scss';

const ExpressCheckoutContainer = ( props ) => {
	const { api, billing, buttonAttributes, isPreview } = props;

	const stripePromise = useMemo( () => {
		return api.loadStripeForExpressCheckout();
	}, [ api ] );

	const useConfirmationToken =
		getExpressCheckoutData( 'flags' )?.isEceUsingConfirmationTokens ?? true;

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
			? {
					paymentMethodTypes,
					...getSetupFutureUsage(),
			  }
			: { paymentMethodCreation: 'manual' } ),
		// ensuring that the total amount is transformed to the correct format.
		amount: ! isPreview
			? transformPrice( billing.cartTotal.value, {
					currency_minor_unit: billing.currency.minorUnit ?? 0,
			  } )
			: 10,
		currency: ! isPreview ? billing.currency.code.toLowerCase() : 'usd',
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
