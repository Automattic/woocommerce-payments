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
	// Regular case: charge is performed with WooPayments and intent has billing details populated.
	const customer = props.billing_details;
	if ( customer && customer.name ) {
		const searchTerm = customer.email
			? `${ customer.name } (${ customer.email })`
			: customer.name;
		const url = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/transactions',
			search: [ searchTerm ],
		} );

		return <Link href={ url }>{ customer.name }</Link>;
	}

	// Special case: charge is performed with a mobile app, and intent billing details are not populated.
	const order = props.order_details;
	if ( order && order.customer_name ) {
		const searchTerm = order.customer_email
			? `${ order.customer_name } (${ order.customer_email })`
			: order.customer_name;
		const url = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/transactions',
			search: [ searchTerm ],
		} );

		return <Link href={ url }>{ order.customer_name }</Link>;
	}

	return <>&ndash;</>;
};

export default CustomerLink;
