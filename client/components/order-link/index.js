/** @format **/

/**
 * External dependencies
 */
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies.
 */

const OrderLink = ( props ) => {
	const { order } = props;
	return order
		? <Link href={ order.url } type="external">{ order.number }</Link>
		: <span>&ndash;</span>;
};

export default OrderLink;
