/**
 * External dependencies
 */
import { useMemo } from 'react';
import { Elements } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import ExpressCheckoutComponent from './express-checkout-component';
import { getExpressCheckoutButtonAppearance } from 'wcpay/express-checkout/utils';
import '../express-checkout-element.scss';

const ExpressCheckoutContainer = ( props ) => {
	const { api, billing } = props;

	const stripePromise = useMemo( () => {
		return api.loadStripe( true );
	}, [ api ] );

	const options = {
		mode: 'payment',
		paymentMethodCreation: 'manual',
		amount: billing.cartTotal.value,
		currency: billing.currency.code.toLowerCase(),
		appearance: getExpressCheckoutButtonAppearance(),
	};

	return (
		<div style={ { minHeight: '50px' } }>
			<Elements stripe={ stripePromise } options={ options }>
				<ExpressCheckoutComponent { ...props } />
			</Elements>
		</div>
	);
};

export default ExpressCheckoutContainer;
