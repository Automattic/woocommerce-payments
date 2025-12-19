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
	getSubscriptionTrialData,
} from '../../utils';
import { transformPrice } from '../../transformers/wc-to-stripe';
import '../express-checkout-element.scss';

const ExpressCheckoutContainer = ( props ) => {
	const { api, billing, buttonAttributes, isPreview } = props;

	const stripePromise = useMemo( () => {
		return api.loadStripeForExpressCheckout();
	}, [ api ] );

	// For subscriptions with free trial, the cart total may be 0.
	// Stripe requires amount > 0 for elements(), so we use the recurring billing amount.
	const subscriptionTrialData = getSubscriptionTrialData();
	let elementAmount = ! isPreview
		? transformPrice( billing.cartTotal.value, {
				currency_minor_unit: billing.currency.minorUnit ?? 0,
		  } )
		: 10;

	if (
		elementAmount === 0 &&
		subscriptionTrialData?.regularBilling?.amount > 0
	) {
		elementAmount = subscriptionTrialData.regularBilling.amount;
	}

	const options = {
		mode: 'payment',
		paymentMethodCreation: 'manual',
		amount: elementAmount,
		currency: ! isPreview ? billing.currency.code.toLowerCase() : 'usd',
		appearance: getExpressCheckoutButtonAppearance( buttonAttributes ),
		locale: getExpressCheckoutData( 'stripe' )?.locale ?? 'en',
	};

	return (
		<div style={ { minHeight: '40px' } }>
			<Elements stripe={ stripePromise } options={ options }>
				<ExpressCheckoutComponent { ...props } />
			</Elements>
		</div>
	);
};

export default ExpressCheckoutContainer;
