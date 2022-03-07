/** @format **/

/**
 * External dependencies
 */
import { Link } from '@woocommerce/components';
import React from 'react';
import { getAdminUrl } from 'wcpay/utils';
import { ChargeBillingDetails } from 'wcpay/types/charges';

/**
 * Internal dependencies.
 */

const CustomerLink = ( props: {
	customer: ChargeBillingDetails;
} ): JSX.Element => {
	const customer = props.customer;
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

	return <>&ndash;</>;
};

export default CustomerLink;
