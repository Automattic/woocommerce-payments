/**
 * Entry point for the Woo Shopping Network Hub admin page.
 *
 * Mounts the React app into the container div emitted by
 * WSN_Hub::render_admin_page() in PHP. The page itself is a vanilla WP
 * submenu page registered via add_submenu_page() — NOT a WC Admin page —
 * which means there's no WC Admin layout chrome competing with our
 * branded PageHeader. The container is the only thing on the page;
 * WsnHubApp owns everything inside it.
 *
 * @format
 */

import { createRoot } from 'react-dom/client';

import './style.scss';
import WsnHubApp from './app';

const mount = () => {
	const container = document.getElementById( 'wcpay-wsn-hub-container' );
	if ( ! container ) {
		return;
	}
	createRoot( container ).render( <WsnHubApp /> );
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
