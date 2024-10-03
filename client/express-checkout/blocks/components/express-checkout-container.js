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
	const { api, billing, buttonAttributes } = props;

	const stripePromise = useMemo( () => {
		return api.loadStripe( true );
	}, [ api ] );

	const options = {
		mode: 'payment',
		paymentMethodCreation: 'manual',
		amount: billing.cartTotal.value,
		currency: billing.currency.code.toLowerCase(),
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
