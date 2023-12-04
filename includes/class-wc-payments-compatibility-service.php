<?php
/**
 * WC_Payments_Compatibility_Service class
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class to send compatibility data to the server.
 */
class WC_Payments_Compatibility_Service {
	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Constructor for WC_Payments_Compatibility_Service.
	 *
	 * @param WC_Payments_API_Client $payments_api_client WooCommerce Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->payments_api_client = $payments_api_client;
	}

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'woocommerce_payments_account_refreshed', [ $this, 'update_compatibility_data' ], 404 );
	}

	/**
	 * Gets the data we need to confirm compatibility and sends it to the server.
	 *
	 * @return void
	 */
	public function update_compatibility_data() {
		$this->payments_api_client->update_compatibility_data(
			[
				'woocommerce_core_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'WooCommerce core version constant not defined.',
			]
		);
	}
}
