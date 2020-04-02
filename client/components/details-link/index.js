/** @format **/

/**
 * External dependencies
 */
import { Link } from '@woocommerce/components';
import { getDetailsURL } from './../details-icon';

const DetailsLink = ( { id, parentSegment, children } ) => (
	id ? (
		<Link href={ getDetailsURL( id, parentSegment ) } >
			{ children }
		</Link>
	) : null
);

export default DetailsLink;
