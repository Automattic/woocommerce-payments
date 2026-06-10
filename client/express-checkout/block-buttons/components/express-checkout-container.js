/**
 * External dependencies
 */
import { useMemo } from 'react';
import { Elements } from '@stripe/react-stripe-js';
import { useSelect } from '@wordpress/data';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import ExpressCheckoutComponent from './express-checkout-component';
import {
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutData,
	shouldUseConfirmationTokens,
	buildPaymentMethodTypes,
	buildStripeElementsOptions,
} from '../../utils';
import { transformPrice } from '../../transformers/wc-to-stripe';
import { getSetupFutureUsageForCart } from '../../utils/subscriptions';
import '../express-checkout-element.scss';
import { WC_STORE_CART } from 'wcpay/checkout/constants';

const ExpressCheckoutContainer = ( props ) => {
	const { api, billing, buttonAttributes } = props;

	const stripePromise = useMemo( () => {
		return api.loadStripeForExpressCheckout();
	}, [ api ] );

	const useConfirmationTokens = shouldUseConfirmationTokens();
	const isManualCaptureEnabled =
		getExpressCheckoutData( 'is_manual_capture' ) ?? false;
	const paymentMethodTypes = useMemo( () => buildPaymentMethodTypes(), [] );
	const cartData = useSelect(
		( selectCart ) => selectCart( WC_STORE_CART )?.getCartData(),
		[]
	);

	// Apply filter to allow modifications (e.g., for trial subscriptions with $0 initial payment)
	const amount = applyFilters(
		'wcpay.express-checkout.total-amount',
		transformPrice( billing.cartTotal.value, {
			currency_minor_unit: billing.currency.minorUnit ?? 0,
		} ),
		cartData
	);

	const options = useMemo(
		() =>
			buildStripeElementsOptions( {
				amount,
				currency: billing.currency.code,
				useConfirmationTokens,
				paymentMethodTypes,
				captureMethod: isManualCaptureEnabled ? 'manual' : undefined,
				setupFutureUsage: getSetupFutureUsageForCart( cartData ),
				appearance:
					getExpressCheckoutButtonAppearance( buttonAttributes ),
			} ),
		[
			amount,
			billing.currency.code,
			useConfirmationTokens,
			paymentMethodTypes,
			isManualCaptureEnabled,
			cartData,
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
