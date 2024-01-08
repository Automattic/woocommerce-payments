<?php
/**
 * Compatibility_Service class
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use WC_Payments_API_Client;
use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * Class to send compatibility data to the server.
 */
class Compatibility_Service {
	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Constructor for Compatibility_Service.
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
		add_action( 'woocommerce_payments_account_refreshed', [ $this, 'update_compatibility_data' ] );
		add_filter( 'wc_payments_get_onboarding_data_args', [ $this, 'add_compatibility_onboarding_data' ] );
	}

	/**
	 * Gets the data we need to confirm compatibility and sends it to the server.
	 *
	 * @return void
	 */
	public function update_compatibility_data() {
		try {
			$this->payments_api_client->update_compatibility_data( $this->get_compatibility_data() );
		} catch ( API_Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// The exception is already logged if logging is on, nothing else needed.
		}
	}

	/**
	 * Adds the compatibility data to the onboarding args.
	 *
	 * @param array $args The args being sent when onboarding.
	 *
	 * @return array
	 */
	public function add_compatibility_onboarding_data( $args ): array {
		$args['compatibility_data'] = $this->get_compatibility_data();
		return $args;
	}

	/**
	 * Gets the compatibility data.
	 *
	 * @return array
	 */
	private function get_compatibility_data(): array {
		return [
			'woopayments_version' => WCPAY_VERSION_NUMBER,
			'woocommerce_version' => WC_VERSION,
		];
	}
}
