/**
 * Internal dependencies
 */
import { renderBNPLCartMessaging } from './product-details';
import './style.scss';

const { registerPlugin } = window.wp.plugins;

registerPlugin( 'bnpl-site-messaging', {
	render: renderBNPLCartMessaging,
	scope: 'woocommerce-checkout',
} );
