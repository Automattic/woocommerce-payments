/** @format **/

/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import './style.scss';
import { Link } from '@woocommerce/components';

const ClickableCell = ( { href, children } ) =>
	href ? (
		<Link
			href={ href }
			className="woocommerce-table__clickable-cell"
			tabIndex="-1"
		>
			{ children }
		</Link>
	) : (
		children
	);

export default ClickableCell;
