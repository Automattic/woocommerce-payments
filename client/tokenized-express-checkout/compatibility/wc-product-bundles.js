/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';

addFilter(
	'wcpay.express-checkout.map-line-items',
	'automattic/wcpay/express-checkout',
	( cartData ) => {
		return {
			...cartData,
			// ensuring that the items that are bundled by another don't appear in the summary.
			// otherwise they might be contributing to the wrong order total, creating errors.
			items: cartData.items.filter(
				( item ) => ! item.extensions?.bundles?.bundled_by
			),
		};
	}
);
