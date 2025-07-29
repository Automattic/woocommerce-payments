<?php
/**
 * Class WC_Payments_Address_Provider
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Automattic\\WooCommerce\\Internal\\AddressProvider\\AbstractAutomatticAddressProvider' ) ) {
	return;
}

use Automattic\WooCommerce\Internal\AddressProvider\AbstractAutomatticAddressProvider;
use WCPay\Logger;

/**
 * Address provider implementation for WooCommerce Payments.
 *
 * @psalm-suppress UndefinedClass
 */
class WC_Payments_Address_Provider extends AbstractAutomatticAddressProvider {
	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $payments_api_client;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client $payments_api_client The API client for making requests.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->id                  = 'woocommerce_payments';
		$this->name                = __( 'WooCommerce Payments', 'woocommerce-payments' );
		$this->payments_api_client = $payments_api_client;
		parent::__construct();
	}


	/**
	 * Get address service JWT token from the WCPay server.
	 *
	 * This method calls the address-autocomplete-token endpoint to retrieve
	 * a JWT token for address autocomplete services.
	 *
	 * Caching and retries and backoff logic is handled by the parent class, if you must override that without fixing it upstream, you should also override `load_jwt`.
	 *
	 * @return string|WP_Error The JWT token on success, WP_Error on failure.
	 */
	public function get_address_service_jwt() {
		try {
			$response = $this->payments_api_client->get_address_autocomplete_token();
			return $response['token'];
		} catch ( \Exception $e ) {
			Logger::error( 'Unexpected error getting address service JWT: ' . $e->getMessage() );
			return new WP_Error(
				'wcpay_address_service_error',
				'An unexpected error occurred while retrieving the address service token.'
			);
		}
	}

	/**
	 * Whether the address provider can send frontend telemetry data.
	 *
	 * @return bool True if telemetry is allowed, false otherwise.
	 */
	public function can_telemetry() {
		// We defer to the global Woo setting.
		return WC_Site_Tracking::is_tracking_enabled();
	}
}
