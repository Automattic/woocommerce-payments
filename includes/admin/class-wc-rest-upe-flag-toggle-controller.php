<?php
/**
 * Class WC_REST_UPE_Flag_Toggle_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

/**
 * REST controller for UPE feature flag. Needs to stay in the codebase to avoid error on plugin update for versions 6.9.2 or earlier.
 */
class WC_REST_UPE_Flag_Toggle_Controller extends WP_REST_Controller {
	/**
	 * Register routes.
	 */
	public function register_routes() {}
}
