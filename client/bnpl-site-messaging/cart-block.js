import { registerPlugin } from '@wordpress/plugins';
import { renderBNPLCartMessaging } from './cart-block-message';

registerPlugin( 'bnpl-site-messaging', {
	render: renderBNPLCartMessaging,
	scope: 'woocommerce-checkout',
} );
