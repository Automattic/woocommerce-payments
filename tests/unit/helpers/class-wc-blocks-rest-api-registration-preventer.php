<?php
/**
 * WC Blocks REST API registration preventer.
 *
 * @package WooCommerce\Payments\Tests
 */

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\RestApi;

/**
 * Class WC_Blocks_REST_API_Registration_Preventer.
 */
class WC_Blocks_REST_API_Registration_Preventer {

	public static function prevent() {
		add_action( 'rest_api_init', [ __CLASS__, 'deregister_wc_blocks_rest_api' ], 5 );
		self::force_route_reregistration();
	}

	public static function stop_preventing() {
		remove_action( 'rest_api_init', [ __CLASS__, 'deregister_wc_blocks_rest_api' ], 5 );
		self::force_route_reregistration();
	}

	/**
	 * Deregister WooCommerce Blocks REST routes to prevent _doing_it_wrong() notices
	 * after calls to rest_do_request().
	 */
	public static function deregister_wc_blocks_rest_api() {
		try {
			/* For WooCommerce Blocks >= 2.6.0: */
			$wc_blocks_rest_api = Package::container()->get( RestApi::class );
			remove_action( 'rest_api_init', [ $wc_blocks_rest_api, 'register_rest_routes' ] );
		} catch ( Exception $e ) {
			/* For WooCommerce Blocks < 2.6.0: */
			remove_action( 'rest_api_init', [ RestApi::class, 'register_rest_routes' ] );
		}
	}

	/**
	 * Force `rest_api_init` to rerun by forcing the server to be recreated.
	 */
	private static function force_route_reregistration() {
		global $wp_rest_server;
		$wp_rest_server = null;
	}
}
