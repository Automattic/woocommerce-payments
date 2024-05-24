/**
 * External dependencies
 */
import { Elements } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import ExpressCheckoutComponent from './express-checkout-component';

const ExpressCheckoutContainer = ( props ) => {
	const { stripe, billing } = props;

	console.log( billing );

	const options = {
		mode: 'payment',
		amount: billing.cartTotal.value,
		currency: billing.currency.code.toLowerCase(),
	};

	return (
		<Elements stripe={ stripe } options={ options }>
			<ExpressCheckoutComponent { ...props } />
		</Elements>
	);
};

export default ExpressCheckoutContainer;
