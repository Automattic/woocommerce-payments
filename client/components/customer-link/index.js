/** @format **/

/**
 * External dependencies
 */
import { Link } from '@woocommerce/components';
import { addQueryArgs } from '@wordpress/url';

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
	const url = addQueryArgs( 'admin.php', {
		page: 'wc-admin',
		path: '/payments/transactions',
		search: [ searchTerm ],
	} );

	return <Link href={ url }>{ customer.name }</Link>;
};

export default CustomerLink;
