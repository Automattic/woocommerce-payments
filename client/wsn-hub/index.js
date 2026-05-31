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

import apiFetch from '@wordpress/api-fetch';
import { createRoot } from 'react-dom/client';

import './style.scss';
import WsnHubApp from './app';

/**
 * Prime wp.apiFetch with a REST nonce BEFORE any component mounts.
 *
 * WP's `rest_cookie_check_errors` requires both the auth cookie AND a valid
 * `X-WP-Nonce` header. The page's admin cookie is present (the admin bar
 * paints correctly), but `window.wpApiSettings` is NOT auto-localized here
 * — declaring `wp-api-fetch` as an asset dependency only loads the script;
 * the inline `wpApiSettings` is only emitted for scripts depending on the
 * older `wp-api` umbrella. Without this priming, the first apiFetch ships
 * with no nonce, the REST controller returns 401 `rest_forbidden` upstream
 * of the permission_callback, and React's mount-time useEffect retries
 * compound the failure into a visible "infinite 401 loop".
 *
 * WSN_Hub::enqueue_admin_assets() localizes the nonce into
 * `window.wcpaySettings.wsn` — read it here and register the middleware
 * before the entry mounts. `nonceEndpoint` enables apiFetch's built-in
 * recovery when the nonce rotates mid-session (WP nonces have a 12–24h tick).
 */
const primeApiFetch = () => {
	const wsnConfig = window.wcpaySettings && window.wcpaySettings.wsn;
	if ( ! wsnConfig ) {
		return;
	}
	if ( wsnConfig.restNonce ) {
		apiFetch.use( apiFetch.createNonceMiddleware( wsnConfig.restNonce ) );
	}
	if ( wsnConfig.restUrl ) {
		apiFetch.use( apiFetch.createRootURLMiddleware( wsnConfig.restUrl ) );
	}
	if ( wsnConfig.restNonceEndpoint ) {
		apiFetch.nonceEndpoint = wsnConfig.restNonceEndpoint;
	}
};

const mount = () => {
	const container = document.getElementById( 'wcpay-wsn-hub-container' );
	if ( ! container ) {
		return;
	}
	createRoot( container ).render( <WsnHubApp /> );
};

primeApiFetch();

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
