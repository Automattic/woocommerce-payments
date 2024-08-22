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
} from 'wcpay/express-checkout/utils';
import '../express-checkout-element.scss';

const ExpressCheckoutContainer = ( props ) => {
	const { api, billing } = props;

	const stripePromise = useMemo( () => {
		return api.loadStripe( true );
	}, [ api ] );

	const options = {
		mode: 'payment',
		paymentMethodCreation: 'manual',
		amount: getExpressCheckoutData( 'has_trial_subscription' )
			? 1300 // TODO: Find a way to get the cart total with trial subscription.
			: billing.cartTotal.value,
		currency: billing.currency.code.toLowerCase(),
		appearance: getExpressCheckoutButtonAppearance(),
	};

	return (
		<Elements stripe={ stripePromise } options={ options }>
			<ExpressCheckoutComponent { ...props } />
		</Elements>
	);
};

export default ExpressCheckoutContainer;
