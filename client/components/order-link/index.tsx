/** @format **/

/**
 * External dependencies
 */
import { Link } from '@woocommerce/components';
import React from 'react';

/**
 * Internal dependencies.
 */

const OrderLink = ( props: {
	order: null | OrderDetails | SubscriptionDetails;
} ): JSX.Element => {
	const order = props.order;
	if ( order && order.number ) {
		return (
			<Link href={ order.url } type="external">
				{ order.number }
			</Link>
		);
	}

	return <span>&ndash;</span>;
};

export default OrderLink;
