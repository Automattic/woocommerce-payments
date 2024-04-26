/**
 * External dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';

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
	const {
		setShippingAddress,
		setBillingData,
		setBillingAddress,
	} = useDispatch( WC_STORE_CART );

	return {
		// Backward compatibility billingData/billingAddress
		billingAddress: customerData.billingAddress || customerData.billingData,
		// Backward compatibility setBillingData/setBillingAddress
		setBillingAddress: setBillingAddress || setBillingData,
		setShippingAddress,
	};
};
