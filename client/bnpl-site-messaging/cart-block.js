/**
 * External dependencies
 */
import { registerPlugin } from '@wordpress/plugins';

/**
 * Internal dependencies
 */
import { renderBNPLCartMessaging } from './cart-block-message';

registerPlugin( 'bnpl-site-messaging', {
	render: renderBNPLCartMessaging,
	scope: 'woocommerce-checkout',
} );
