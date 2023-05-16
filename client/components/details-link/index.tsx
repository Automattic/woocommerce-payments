/** @format **/

/**
 * External dependencies
 */
import InfoOutlineIcon from 'gridicons/dist/info-outline';
import { Link } from '@woocommerce/components';
import { getAdminUrl } from 'wcpay/utils';
import React from 'react';

interface DetailsLinkProps {
	/**
	 * The ID of the object to link to.
	 */
	id: string;

	/**
	 * The parent segment of the URL. e.g. 'transactions' or 'disputes'.
	 */
	parentSegment: string;
}

export const getDetailsURL = ( id: string, parentSegment: string ): string =>
	getAdminUrl( {
		page: 'wc-admin',
		path: `/payments/${ parentSegment }/details`,
		id,
	} );

const DetailsLink: React.FunctionComponent< DetailsLinkProps > = ( {
	id,
	parentSegment,
} ) => (
	<Link href={ getDetailsURL( id, parentSegment ) }>
		<InfoOutlineIcon size={ 18 } />
	</Link>
);

export default DetailsLink;
