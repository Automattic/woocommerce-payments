/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { WC_STORE_CART } from 'wcpay/checkout/constants';

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
