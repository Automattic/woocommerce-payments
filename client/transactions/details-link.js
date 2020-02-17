/** @format **/

/**
 * External dependencies
 */
import Gridicon from 'gridicons';
import { addQueryArgs } from '@wordpress/url';
import { Link } from '@woocommerce/components';

const DetailsLink = ( { chargeId } ) => {
	// TODO: come up with a link generator utility (woocommerce-payments#229)

	const detailsUrl = addQueryArgs(
		'admin.php',
		{
			page: 'wc-admin',
			path: '/payments/transactions/details',
			id: chargeId,
		}
	);

	return chargeId ? (
		<Link className="transactions-list__details-button" href={ detailsUrl } >
			<Gridicon icon="info-outline" size={ 18 } />
		</Link>
	) : '';
};

export default DetailsLink;
