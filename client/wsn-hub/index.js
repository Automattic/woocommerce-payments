/**
 * Page component for the Woo Shopping Network Hub.
 *
 * This is the default export consumed by client/index.js when it registers the
 * Hub via the `woocommerce_admin_pages_list` filter. WC Admin handles the routing
 * and mount; this module just exports the React component to render at
 * /payments/shopping-network.
 *
 * @format
 */

import './style.scss';
import WsnHubApp from './app';

export default WsnHubApp;
