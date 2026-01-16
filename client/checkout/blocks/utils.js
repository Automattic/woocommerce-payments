/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { WC_STORE_CART } from 'wcpay/checkout/constants';
import { getTerms, isLinkEnabled } from '../utils/upe';
import { getUPEConfig } from 'wcpay/utils/checkout';

/**
 *
 * Custom React hook that provides customer data and related functions for managing customer information.
 * The hook retrieves customer data from the WC_STORE_CART selector and dispatches actions to modify billing and shipping addresses.
 *
 * @return {Object} An object containing customer data and functions for managing customer information.
 */
export const useCustomerData = () => {
	const customerData = useSelect( ( select ) =>
		select( WC_STORE_CART ).getCustomerData()
	);

	// Backward compatibility billingData/billingAddress
	return customerData.billingAddress || customerData.billingData;
};

/**
 * Returns the prepared set of options needed to initialize the Stripe elements for UPE in Block Checkout.
 * The initial options have all the fields set to 'never' to hide them from the UPE, because all the
 * information is already collected in the checkout form. Additionally, the options are updated with
 * the terms text if needed.
 *
 * @param {boolean} shouldSavePayment Whether the payment method should be saved.
 * @param {Object} paymentMethodsConfig The payment methods config object.
 *
 * @return {Object} The options object for the Stripe elements.
 */
export const getStripeElementOptions = (
	shouldSavePayment,
	paymentMethodsConfig
) => {
	const options = {
		fields: {
			billingDetails: {
				name: 'never',
				email: 'never',
				phone: 'never',
				address: {
					country: 'never',
					line1: 'never',
					line2: 'never',
					city: 'never',
					state: 'never',
					postalCode: 'never',
				},
			},
		},
		wallets: {
			applePay: 'never',
			googlePay: 'never',
			link: isLinkEnabled( paymentMethodsConfig ) ? 'auto' : 'never',
		},
	};

	const showTerms =
		shouldSavePayment || getUPEConfig( 'cartContainsSubscription' )
			? 'always'
			: 'never';

	options.terms = getTerms( paymentMethodsConfig, showTerms );

	return options;
};
