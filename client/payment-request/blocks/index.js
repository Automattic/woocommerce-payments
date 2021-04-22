/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_PAYMENT_REQUEST } from '../../checkout/constants';
import { PaymentRequestExpress } from './payment-request-express';
import { applePayImage } from './apple-pay-preview';
import { loadStripe } from '../stripe-utils';

import { getConfig } from '../../utils/checkout';
import { getPaymentRequestData } from '../utils';

const ApplePayPreview = () => <img src={ applePayImage } alt="" />;

const componentStripePromise = loadStripe();

let isStripeInitialized = false,
	canPay = false;

// Initialise stripe API client and determine if payment method can be used
// in current environment (e.g. geo + shopper has payment settings configured).
function paymentRequestAvailable( { currencyCode, totalPrice } ) {
	// Stripe only supports carts of greater value than 30 cents.
	if ( 30 > totalPrice ) {
		return false;
	}

	// If we've already initialised, return the cached results.
	if ( isStripeInitialized ) {
		return canPay;
	}

	return componentStripePromise.then( ( stripe ) => {
		if ( null === stripe ) {
			isStripeInitialized = true;
			return canPay;
		}
		if ( stripe.error && stripe.error instanceof Error ) {
			throw stripe.error;
		}
		// Do a test payment to confirm if payment method is available.
		const paymentRequest = stripe.paymentRequest( {
			total: {
				label: 'Total',
				amount: totalPrice,
				pending: true,
			},
			country: getPaymentRequestData( 'checkout' )?.country_code,
			currency: currencyCode,
		} );
		return paymentRequest.canMakePayment().then( ( result ) => {
			canPay = !! result;
			isStripeInitialized = true;
			return canPay;
		} );
	} );
}

const paymentRequestPaymentMethod = ( api ) => ( {
	name: PAYMENT_METHOD_NAME_PAYMENT_REQUEST,
	content: (
		<PaymentRequestExpress api={ api } stripe={ componentStripePromise } />
	),
	edit: <ApplePayPreview />,
	canMakePayment: ( cartData ) =>
		paymentRequestAvailable( {
			currencyCode: cartData?.cartTotals?.currency_code?.toLowerCase(),
			totalPrice: parseInt( cartData?.cartTotals?.total_price || 0, 10 ),
		} ),
	paymentMethodId: PAYMENT_METHOD_NAME_PAYMENT_REQUEST,
	supports: {
		features: getConfig( 'features' ),
	},
} );

export default paymentRequestPaymentMethod;
