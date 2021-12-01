/** @format **/

/**
 * External dependencies
 */
import { Link } from '@woocommerce/components';
import { getAdminUrl } from 'wcpay/utils';

/**
 * Internal dependencies.
 */

const CustomerLink = ( props ) => {
	const { customer } = props;

	if ( ( customer || {} ).name === undefined ) {
		return '-';
	}

	let searchTerm = customer.name;
	if ( customer.email ) {
		searchTerm = `${ customer.name } (${ customer.email })`;
	}
	const url = getAdminUrl( {
		page: 'wc-admin',
		path: '/payments/transactions',
		search: [ searchTerm ],
	} );

	return <Link href={ url }>{ customer.name }</Link>;
};

export default CustomerLink;
