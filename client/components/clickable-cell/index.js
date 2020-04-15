/** @format **/

/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import './style.scss';
import { getDetailsURL } from '../details-link';
import { Link } from '@woocommerce/components';

const ClickableCell = ( { id, parentSegment, children } ) => (
	id ? (
		<Link href={ getDetailsURL( id, parentSegment ) } className="woocommerce-table__clickable-cell" tabIndex="-1">
			{ children }
		</Link>
	) : null
);

export default ClickableCell;
