/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import InfoOutlineIcon from 'gridicons/dist/info-outline';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import { getAdminUrl } from 'wcpay/utils';

/**
 * The parent segment is the first part of the URL after the /payments/ path.
 */
type ParentSegment = 'deposits' | 'transactions' | 'disputes';

export const getDetailsURL = (
	id: string,
	parentSegment: ParentSegment
): string =>
	getAdminUrl( {
		page: 'wc-admin',
		path: `/payments/${ parentSegment }/details`,
		id,
	} );

interface DetailsLinkProps {
	/**
	 * The ID of the object to link to.
	 */
	id?: string;
	/**
	 * The parent segment is the first part of the URL after the /payments/ path.
	 */
	parentSegment: ParentSegment;
}
const DetailsLink: React.FC< DetailsLinkProps > = ( { id, parentSegment } ) =>
	id ? (
		<Link href={ getDetailsURL( id, parentSegment ) }>
			<InfoOutlineIcon size={ 18 } />
		</Link>
	) : null;

export default DetailsLink;
