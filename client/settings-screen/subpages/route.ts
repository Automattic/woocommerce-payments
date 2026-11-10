/**
 * External dependencies
 */
import { notFound } from '@wordpress/route';

/**
 * Internal dependencies
 */
import { getSubPage } from './pages';

export const route = {
	beforeLoad: ( { params }: { params: { subpage?: string } } ) => {
		if ( ! getSubPage( params.subpage ) ) {
			throw notFound();
		}
	},
};
