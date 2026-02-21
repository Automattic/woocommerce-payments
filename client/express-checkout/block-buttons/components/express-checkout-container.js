/**
 * External dependencies
 */
import { useMemo } from 'react';
import { Elements } from '@stripe/react-stripe-js';
import { select } from '@wordpress/data';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import ExpressCheckoutComponent from './express-checkout-component';
import {
	getExpressCheckoutButtonAppearance,
	getStripeElementsMode,
	shouldUseConfirmationTokens,
	buildPaymentMethodTypes,
	buildStripeElementsOptions,
} from '../../utils';
import { transformPrice } from '../../transformers/wc-to-stripe';
import '../express-checkout-element.scss';
import { WC_STORE_CART } from 'wcpay/checkout/constants';

const ExpressCheckoutContainer = ( props ) => {
	const { api, billing, buttonAttributes } = props;

	const stripePromise = useMemo( () => {
		return api.loadStripeForExpressCheckout();
	}, [ api ] );

	const useConfirmationTokens = shouldUseConfirmationTokens();
	const paymentMethodTypes = useMemo( () => buildPaymentMethodTypes(), [] );

	const amount = applyFilters(
		'wcpay.express-checkout.total-amount',
		transformPrice( billing.cartTotal.value, {
			currency_minor_unit: billing.currency.minorUnit ?? 0,
		} ),
		select( WC_STORE_CART )?.getCartData()
	);

	const options = useMemo(
		() =>
			buildStripeElementsOptions( {
				amount,
				currency: billing.currency.code,
				useConfirmationTokens,
				paymentMethodTypes,
				mode: getStripeElementsMode(),
				appearance: getExpressCheckoutButtonAppearance(
					buttonAttributes
				),
			} ),
		[
			amount,
			billing.currency.code,
			useConfirmationTokens,
			paymentMethodTypes,
			buttonAttributes,
		]
	);

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
