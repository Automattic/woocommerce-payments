/** @format **/

/**
 * External dependencies
 */
import * as React from 'react';
import * as Gridicon from 'gridicons';
import { addQueryArgs } from '@wordpress/url';
import { Link } from '@woocommerce/components';

export const getDetailsURL = ( id: string, parentSegment: string ) =>
	addQueryArgs( 'admin.php', {
		page: 'wc-admin',
		path: `/payments/${ parentSegment }/details`,
		id,
	} );

type DetailsLinkParams = {
	id?: string;
	parentSegment: string;
};

const DetailsLink = ( { id, parentSegment }: DetailsLinkParams ) =>
	id ? (
		<Link href={ getDetailsURL( id, parentSegment ) }>
			<Gridicon icon="info-outline" size={ 18 } />
		</Link>
	) : null;

export default DetailsLink;
