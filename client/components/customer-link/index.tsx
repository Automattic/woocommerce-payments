/** @format **/

/**
 * External dependencies
 */
import { Link } from '@woocommerce/components';
import React from 'react';

/**
 * Internal dependencies.
 */
import { getAdminUrl } from 'wcpay/utils';
import { ChargeBillingDetails } from 'wcpay/types/charges';

const CustomerLink = ( props: {
	billing_details: null | ChargeBillingDetails;
	order_details: null | OrderDetails;
} ): JSX.Element => {
	// Depending on the transaction chanel, charge billing details might be missing, and we have to rely on order for those.
	const name =
		props.billing_details?.name ||
		props.order_details?.customer_name ||
		null;
	if ( name ) {
		const email =
			props.billing_details?.email ||
			props.order_details?.customer_email ||
			null;
		const url = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/transactions',
			search: [ email ? `${ name } (${ email })` : name ],
		} );

		return <Link href={ url }>{ name }</Link>;
	}

	return <>&ndash;</>;
};

export default CustomerLink;
