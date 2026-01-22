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

	const options = {
		mode: 'payment',
		...( useConfirmationToken
			? { paymentMethodTypes: [ 'card' ] }
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
				<ExpressCheckoutComponent { ...props } />
			</Elements>
		</div>
	);
};

export default ExpressCheckoutContainer;
