/** @format **/

/**
 * External dependencies
 */
import Gridicon from 'gridicons';
import { addQueryArgs } from '@wordpress/url';
import { Link } from '@woocommerce/components';

export const getDetailsURL = ( id, parentSegment ) =>
	addQueryArgs( 'admin.php', {
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
