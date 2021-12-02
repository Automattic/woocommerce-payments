/** @format **/

/**
 * External dependencies
 */
import Gridicon from 'gridicons';
import { Link } from '@woocommerce/components';
import { getAdminUrl } from 'wcpay/utils';

export const getDetailsURL = ( id, parentSegment ) =>
	getAdminUrl( {
		page: 'wc-admin',
		path: `/payments/${ parentSegment }/details`,
		id,
	} );

const DetailsLink = ( { id, parentSegment } ) =>
	id ? (
		<Link href={ getDetailsURL( id, parentSegment ) }>
			<Gridicon icon="info-outline" size={ 18 } />
		</Link>
	) : null;

export default DetailsLink;
