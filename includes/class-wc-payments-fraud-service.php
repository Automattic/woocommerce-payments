<?php
/**
 * WC_Payments_Fraud_Service class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

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
	 * Constructor for WC_Payments_Fraud_Service.
	 *
	 * @param WC_Payments_API_Client       $payments_api_client      - WooCommerce Payments API client.
	 * @param WC_Payments_Customer_Service $customer_service         - Customer class instance.
	 * @param WC_Payments_Account          $account                  - Account class instance.
	 * @param WC_Payments_Session_Service  $session_service          - Session Service class instance.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Account $account,
		WC_Payments_Session_Service $session_service
	) {
		$this->payments_api_client = $payments_api_client;
		$this->customer_service    = $customer_service;
		$this->account             = $account;
		$this->session_service     = $session_service;
	}

	/**
	 * Initializes this class's hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'wcpay_prepare_fraud_config', [ $this, 'prepare_fraud_config' ], 10, 2 );
		add_action( 'init', [ $this, 'link_session_if_user_just_logged_in' ] );
		add_action( 'admin_print_footer_scripts', [ $this, 'add_sift_js_tracker' ] );
	}

	/**
	 * Prepares the fraud config for a service.
	 *
	 * @param array  $config     Existing config data for the given anti-fraud service.
	 * @param string $service_id Identifier of the anti-fraud service provider.
	 *
	 * @return array|NULL Array with all the required data to initialize the anti-fraud script, or NULL if the service shouldn't be used.
	 */
	public function prepare_fraud_config( $config, $service_id ) {
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
	 * @return array|NULL Assoc array, ready for the client to consume, or NULL if the client shouldn't enqueue this script.
	 */
	private function prepare_sift_config( $config ) {
		// The server returns both production and sandbox beacon keys. Use the sandbox one if test mode is enabled.
		if ( WC_Payments::mode()->is_test() ) {
			$config['beacon_key'] = $config['sandbox_beacon_key'];
		}
		unset( $config['sandbox_beacon_key'] );

		$wpcom_blog_id = $this->payments_api_client->get_blog_id();
		if ( ! $wpcom_blog_id ) {
			// Don't enqueue the SIFT script if Jetpack hasn't been connected yet.
			return null;
		}

		$config['user_id'] = '';

		if ( is_user_logged_in() ) {
			if ( is_admin() ) {
				$config['user_id'] = $this->account->get_stripe_account_id();
			} else {
				$customer_id = $this->customer_service->get_customer_id_by_user_id( get_current_user_id() );
				if ( isset( $customer_id ) ) {
					$config['user_id'] = $customer_id;
				}
			}
		}

		$config['session_id'] = $this->session_service->get_sift_session_id();

		return $config;
	}

	/**
	 * Called after the WooCommerce session has been initialized. Check if the current user has just logged in,
	 * and sends that information to the server to link the current browser session with the user.
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

		$fraud_config = $this->account->get_fraud_services_config();
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
	 * Adds the Sift JS page tracker if needed. See the comments for the detailed logic.
	 *
	 * @return  void
	 */
	public function add_sift_js_tracker() {
		if ( ! isset( $_GET['wcpay-connection-success'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			// Only enqueue the tracker if the merchant has just finished the Stripe KYC.
			return;
		}

		$fraud_config = $this->account->get_fraud_services_config();
		if ( ! isset( $fraud_config['sift'] ) ) {
			// Abort if Sift is not enabled for this account.
			return;
		}

		if ( isset( $_GET['path'] ) && strpos( $_GET['path'], '/payments/' ) === 0 ) { // phpcs:ignore WordPress.Security
			// If the current page is a WCPay dashboard, there's separate logic that will include the Sift JS tracker on its own.
			return;
		}

		?>
		<script type="text/javascript">
			var src = 'https://cdn.sift.com/s.js';

			var _sift = ( window._sift = window._sift || [] );
			_sift.push( [ '_setAccount', '<?php echo esc_attr( $fraud_config['sift']['beacon_key'] ); ?>' ] );
			_sift.push( [ '_setUserId', '<?php echo esc_attr( $fraud_config['sift']['user_id'] ); ?>' ] );
			_sift.push( [ '_setSessionId', '<?php echo esc_attr( $fraud_config['sift']['session_id'] ); ?>' ] );
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
}
