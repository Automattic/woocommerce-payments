<?php
/**
 * WC_Payments_Fraud_Service class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Database_Cache;
use WCPay\Exceptions\API_Exception;
use WCPay\Logger;

/**
 * Class which includes all the fraud-specific logic.
 */
class WC_Payments_Fraud_Service {
	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WC_Payments_Customer instance for working with customer information
	 *
	 * @var WC_Payments_Customer_Service
	 */
	private $customer_service;

	/**
	 * WC_Payments_Session_Service instance for working with session information
	 *
	 * @var WC_Payments_Session_Service
	 */
	private $session_service;

	/**
	 * Cache util for managing the database cached data.
	 *
	 * @var Database_Cache
	 */
	private $database_cache;

	/**
	 * Constructor for WC_Payments_Fraud_Service.
	 *
	 * @param WC_Payments_API_Client       $payments_api_client      - WooCommerce Payments API client.
	 * @param WC_Payments_Customer_Service $customer_service         - Customer class instance.
	 * @param WC_Payments_Account          $account                  - Account class instance.
	 * @param WC_Payments_Session_Service  $session_service          - Session Service class instance.
	 * @param Database_Cache               $database_cache           - Database cache instance.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Account $account,
		WC_Payments_Session_Service $session_service,
		Database_Cache $database_cache
	) {
		$this->payments_api_client = $payments_api_client;
		$this->customer_service    = $customer_service;
		$this->account             = $account;
		$this->session_service     = $session_service;
		$this->database_cache      = $database_cache;
	}

	/**
	 * Initializes this class's hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'init', [ $this, 'link_session_if_user_just_logged_in' ] );
		add_action( 'admin_print_footer_scripts', [ $this, 'add_sift_js_tracker_in_admin' ] );
	}

	/**
	 * Gets the various anti-fraud services that must be included on every WCPay-related page.
	 *
	 * @return array Assoc array. Each key is the slug of a fraud service that must be incorporated to every page.
	 *               The value is a service-specific config for it.
	 */
	public function get_fraud_services_config(): array {
		$raw_config = null;

		// First, try to get the config from the account data.
		// This config takes precedence since it can be merchant-specific.
		// We expect this entry to contain everything needed for the fraud services to work.
		$account = $this->account->get_cached_account_data();
		if ( ! empty( $account ) && isset( $account['fraud_services'] ) ) {
			$raw_config = $account['fraud_services'];
		}

		// If the fraud services config is not available in the account data, try to get it from the server.
		// This is a public, merchant-agnostic config.
		// We expect the server to provide everything needed for the fraud services to work.
		// If we've been given an empty array, we respect that; so no empty checks.
		if ( is_null( $raw_config ) ) {
			$raw_config = $this->get_cached_fraud_services();
		}

		if ( is_null( $raw_config ) ) {
			// This was the default before adding new anti-fraud providers, preserve backwards-compatibility.
			$raw_config = [ 'stripe' => [] ];
		}

		$services_config = [];
		foreach ( $raw_config as $service_id => $config ) {
			if ( ! is_array( $config ) ) {
				$config = [];
			}

			// Apply our internal logic before allowing others to have a say through filters.
			$config = $this->prepare_fraud_config( $config, $service_id );

			$services_config[ $service_id ] = apply_filters( 'wcpay_prepare_fraud_config', $config, $service_id );
		}

		return $services_config;
	}

	/**
	 * Check if the current user has just logged in,
	 * and sends that information to the server to link the current browser session with the user.
	 *
	 * Called after the WooCommerce session has been initialized.
	 *
	 * @return void
	 *
	 * @throws Exception In case the main gateway class has not been initialized yet.
	 *                   This means that the method is called before the `init` hook.
	 */
	public function link_session_if_user_just_logged_in() {
		$wpcom_blog_id = $this->payments_api_client->get_blog_id();
		if ( ! $wpcom_blog_id ) {
			// Don't do anything if Jetpack hasn't been connected yet.
			return;
		}

		if ( ! $this->session_service->user_just_logged_in() ) {
			return;
		}

		$fraud_config = $this->get_fraud_services_config();
		if ( ! isset( $fraud_config['sift'] ) ) {
			// If Sift isn't enabled, we don't need to link the session.
			return;
		}

		// The session changed during the current page load, for example if the user just logged in.
		// In this case, send the old session's customer ID alongside the new user_id so SIFT can link them.
		$customer_id = $this->customer_service->get_customer_id_by_user_id( get_current_user_id() );
		if ( ! isset( $customer_id ) ) {
			return;
		}

		try {
			$this->session_service->link_current_session_to_customer( $customer_id );
		} catch ( API_Exception $e ) {
			Logger::log( '[Tracking] Error when linking session with user: ' . $e->getMessage() );
		}
	}

	/**
	 * Adds the Sift JS page tracker in the WP admin area, if needed.
	 *
	 * @return  void
	 *
	 * @throws Exception In case the main gateway class has not been initialized yet.
	 *                    This means that the method is called before the `init` hook.
	 */
	public function add_sift_js_tracker_in_admin() {
		// If the current page is a WooPayments dashboard page bail as there's separate logic
		// that will include the Sift JS tracker on its own.
		if ( isset( $_GET['path'] ) && strpos( $_GET['path'], '/payments/' ) === 0 ) { // phpcs:ignore WordPress.Security
			return;
		}

		// Bail if this is not a WooCommerce admin page.
		if ( is_callable( '\Automattic\WooCommerce\Admin\PageController::is_admin_or_embed_page' )
			&& ! \Automattic\WooCommerce\Admin\PageController::is_admin_or_embed_page() ) {
			return;
		}

		// Bail if Sift is not enabled (either globally or for the current account).
		$fraud_services_config = $this->get_fraud_services_config();
		if ( ! isset( $fraud_services_config['sift'] ) ) {
			return;
		}
		?>
		<script type="text/javascript">
			var src = 'https://cdn.sift.com/s.js';

			var _sift = ( window._sift = window._sift || [] );
			_sift.push( [ '_setAccount', '<?php echo esc_attr( $fraud_services_config['sift']['beacon_key'] ); ?>' ] );
			_sift.push( [ '_setUserId', '<?php echo esc_attr( $fraud_services_config['sift']['user_id'] ); ?>' ] );
			_sift.push( [ '_setSessionId', '<?php echo esc_attr( $fraud_services_config['sift']['session_id'] ); ?>' ] );
			_sift.push( [ '_trackPageview' ] );

			if ( ! document.querySelector( '[src="' + src + '"]' ) ) {
				var script = document.createElement( 'script' );
				script.src = src;
				script.async = true;
				document.body.appendChild( script );
			}
		</script>
		<?php
	}

	/**
	 * Checks if the cached fraud services config can be used.
	 *
	 * @param bool|string|array $fraud_services_config The cached config.
	 *
	 * @return bool True if the cached fraud services config is valid.
	 */
	public function is_valid_cached_fraud_services( $fraud_services_config ): bool {
		// null/false means no config has been cached.
		if ( null === $fraud_services_config || false === $fraud_services_config ) {
			return false;
		}

		// Non-array values are not expected.
		if ( ! is_array( $fraud_services_config ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Gets and caches the public fraud services config.
	 *
	 * @return array|null Fraud services config or null if failed to retrieve fraud services config.
	 */
	private function get_cached_fraud_services(): ?array {
		return $this->database_cache->get_or_add(
			Database_Cache::FRAUD_SERVICES_KEY,
			function () {
				$fraud_services = $this->fetch_public_fraud_services_config();

				if ( ! $this->is_valid_cached_fraud_services( $fraud_services ) ) {
					return false;
				}

				// Sanitize the config just to be safe by applying a sweeping `sanitize_text_field` on all the data.
				// This is OK to do since we are not accepting data entries with HTML.
				return WC_Payments_Utils::array_map_recursive(
					$fraud_services,
					function ( $value ) {
						// Only apply `sanitize_text_field()` to string values since this function will cast to string.
						if ( is_string( $value ) ) {
							return sanitize_text_field( $value );
						}

						return $value;
					}
				);
			},
			[ $this, 'is_valid_cached_fraud_services' ]
		);
	}

	/**
	 * Prepares the fraud config for a service.
	 *
	 * @param array  $config     Existing config data for the given anti-fraud service.
	 * @param string $service_id Identifier of the anti-fraud service provider.
	 *
	 * @return array|NULL Array with all the required data to initialize the anti-fraud script, or NULL if the service shouldn't be used.
	 */
	private function prepare_fraud_config( array $config, string $service_id ): ?array {
		switch ( $service_id ) {
			case 'sift':
				return $this->prepare_sift_config( $config );
		}

		return $config;
	}

	/**
	 * Adds site-specific config needed to initialize the SIFT anti-fraud JS.
	 *
	 * @param array $config Associative array with the SIFT-related configuration returned from the server.
	 *
	 * @return array|null Assoc array, ready for the client to consume, or NULL if the client shouldn't enqueue this script.
	 */
	private function prepare_sift_config( array $config ): ?array {
		$test_mode = false;
		try {
			$test_mode = WC_Payments::mode()->is_test();
		} catch ( Exception $e ) {
			Logger::log( sprintf( 'WooPayments JS settings: Could not determine if WCPay should be in test mode! Message: %s', $e->getMessage() ), 'warning' );
		}

		// The server returns both production and sandbox beacon keys. Use the sandbox one if test mode is enabled.
		if ( $test_mode && isset( $config['sandbox_beacon_key'] ) ) {
			$config['beacon_key'] = $config['sandbox_beacon_key'];
		}
		unset( $config['sandbox_beacon_key'] );

		$config['user_id']    = $this->get_sift_user_id();
		$config['session_id'] = $this->session_service->get_sift_session_id();

		return $config;
	}

	/**
	 * Get the Sift user ID to use for the current request.
	 *
	 * @return string The Sift user ID to use for the current request. Empty string if we couldn't determine one.
	 */
	private function get_sift_user_id(): string {
		$user_id = '';

		if ( ! is_user_logged_in() ) {
			return $user_id;
		}

		if ( is_admin() ) {
			// In the WP admin we deal with merchant accounts, not customers.
			$user_id = $this->account->get_stripe_account_id() ?? '';
		} else {
			$customer_id = $this->customer_service->get_customer_id_by_user_id( get_current_user_id() );
			if ( isset( $customer_id ) ) {
				$user_id = $customer_id;
			}
		}

		return $user_id;
	}

	/**
	 * Fetches public fraud services config from the server.
	 *
	 * @return array|null Fraud services config or null.
	 */
	private function fetch_public_fraud_services_config(): ?array {
		// Build the endpoint URL.
		$url = WC_Payments_API_Client::ENDPOINT_BASE . '/' . WC_Payments_API_Client::ENDPOINT_REST_BASE . '/' . WC_Payments_API_Client::FRAUD_SERVICES_API;

		$response = wp_remote_get(
			$url,
			[
				'user-agent' => 'WCPay/' . WCPAY_VERSION_NUMBER . '; ' . get_bloginfo( 'url' ),
			]
		);

		// Return early if there is an error.
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$config = null;
		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			// Decode the results, falling back to an empty array.
			$config = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $config ) || ! is_array( $config ) ) {
				$config = null;
			}
		}

		return $config;
	}
}
