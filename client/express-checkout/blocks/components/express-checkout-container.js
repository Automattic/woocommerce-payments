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
	getStripeElementsMode,
} from '../../utils';
import { transformPrice } from '../../transformers/wc-to-stripe';
import '../express-checkout-element.scss';

const ExpressCheckoutContainer = ( props ) => {
	const { api, billing, buttonAttributes } = props;

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
		mode: getStripeElementsMode(),
		...( useConfirmationToken
			? { paymentMethodTypes }
			: { paymentMethodCreation: 'manual' } ),
		// ensuring that the total amount is transformed to the correct format.
		amount: transformPrice( billing.cartTotal.value, {
			currency_minor_unit: billing.currency.minorUnit ?? 0,
		} ),
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
