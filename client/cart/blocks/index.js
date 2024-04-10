/**
 * Internal dependencies
 */
import { renderBNPLCartMessaging } from './product-details';

const { registerPlugin } = window.wp.plugins;

// Register BNPL site messaging on the cart block.
registerPlugin( 'bnpl-site-messaging', {
	render: renderBNPLCartMessaging,
	scope: 'woocommerce-checkout',
} );
