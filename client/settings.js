/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';

addFilter( 'woocommerce_admin_notices_to_show', 'plugin-domain', notices => {
	return [
		...notices,
		[ 'wcpay-test-mode-notice', null, null ],
	];
} );
