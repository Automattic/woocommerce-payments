/** @format **/

/**
 * External dependencies
 */
import Gridicon from 'gridicons';
import { addQueryArgs } from '@wordpress/url';
import { Link } from '@woocommerce/components';

const DetailsLink = ( { id, parentSegment } ) => {
	// TODO: come up with a link generator utility (woocommerce-payments#229)

	const detailsUrl = addQueryArgs(
		'admin.php',
		{
			page: 'wc-admin',
			path: `/payments/${ parentSegment }/details`,
			id,
		}
	);

	return id ? (
		<Link href={ detailsUrl } >
			<Gridicon icon="info-outline" size={ 18 } />
		</Link>
	) : null;
};

export default DetailsLink;
