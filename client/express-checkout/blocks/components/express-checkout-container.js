/**
 * External dependencies
 */
import { Elements } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import ExpressCheckoutComponent from './express-checkout-component';

const ExpressCheckoutContainer = ( props ) => {
	const { stripe } = props;

	const options = {
		mode: 'payment',
		amount: 1099,
		currency: 'usd',
	};

	return (
		<Elements stripe={ stripe } options={ options }>
			<ExpressCheckoutComponent />
		</Elements>
	);
};

export default ExpressCheckoutContainer;
