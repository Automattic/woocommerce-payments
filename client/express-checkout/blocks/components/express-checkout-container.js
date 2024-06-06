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

	const options = {
		mode: 'payment',
		paymentMethodCreation: 'manual',
		amount: billing.cartTotal.value,
		currency: billing.currency.code.toLowerCase(),
	};

	return (
		<div style={ { minHeight: '50px' } }>
			<Elements stripe={ stripe } options={ options }>
				<ExpressCheckoutComponent { ...props } />
			</Elements>
		</div>
	);
};

export default ExpressCheckoutContainer;
