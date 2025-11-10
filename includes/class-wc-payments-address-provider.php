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
use WCPay\Database_Cache;
use WCPay\Logger;

/**
 * Address provider implementation for WooCommerce Payments.
 *
 * @psalm-suppress UndefinedClass
 */
class WC_Payments_Address_Provider extends AbstractAutomatticAddressProvider {
	/**
	 * Placeholder value to use in the cache when the token retrieval fails.
	 */
	const INVALID_TOKEN = 'INVALID_TOKEN';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $payments_api_client;

	/**
	 * Payments account service.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * Database cache instance.
	 *
	 * @var Database_Cache
	 */
	private $database_cache;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client $payments_api_client The API client for making requests.
	 * @param WC_Payments_Account    $account The payments account service.
	 * @param Database_Cache         $database_cache The database cache instance.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Account $account, Database_Cache $database_cache ) {
		$this->id                  = 'woocommerce_payments';
		$this->name                = __( 'WooCommerce Payments', 'woocommerce-payments' );
		$this->payments_api_client = $payments_api_client;
		$this->account             = $account;
		$this->database_cache      = $database_cache;
		parent::__construct();
	}

	/**
	 * Checks if the core setting is enabled before loading scripts.
	 * The parent method does not check this (will be patched and this override can be removed when WC 10.4 is released)
	 */
	public function load_scripts() {
		if ( wc_string_to_bool( get_option( 'woocommerce_address_autocomplete_enabled', 'no' ) ) === true ) {
			parent::load_scripts();
		}
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
		$token = $this->database_cache->get_or_add(
			Database_Cache::ADDRESS_AUTOCOMPLETE_JWT_KEY,
			function () {
				if ( ! $this->account->is_stripe_connected() ) {
					return self::INVALID_TOKEN;
				}

				try {
					$response = $this->payments_api_client->get_address_autocomplete_token();
					return $response['token'] ?? self::INVALID_TOKEN;
				} catch ( \Exception $e ) {
					Logger::error( 'Unexpected error getting address service JWT: ' . $e->getMessage() );
					return self::INVALID_TOKEN;
				}
			},
			'__return_true'
		);

		if ( self::INVALID_TOKEN === $token ) {
			return new WP_Error(
				'wcpay_address_service_error',
				'An unexpected error occurred while retrieving the address service token.'
			);
		}

		return $token;
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
