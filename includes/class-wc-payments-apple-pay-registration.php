<?php
/**
 * Class WC_Payments_Apple_Pay_Registration
 *
 * Adapted from WooCommerce Stripe Gateway extension.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WCPay\Logger;
use WCPay\Exceptions\API_Exception;
use WCPay\Tracker;

/**
 * WC_Payments_Apple_Pay_Registration class.
 */
class WC_Payments_Apple_Pay_Registration {

	const DOMAIN_ASSOCIATION_FILE_NAME = 'apple-developer-merchantid-domain-association';
	const DOMAIN_ASSOCIATION_FILE_DIR  = '.well-known';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * The WCPay account object.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WC_Payment_Gateway_WCPay instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Current domain name.
	 *
	 * @var string
	 */
	private $domain_name;

	/**
	 * Stores Apple Pay domain verification issues.
	 *
	 * @var string
	 */
	private $apple_pay_verify_notice;

	/**
	 * Initialize class actions.
	 *
	 * @param WC_Payments_API_Client   $payments_api_client WooCommerce Payments API client.
	 * @param WC_Payments_Account      $account WooCommerce Payments account.
	 * @param WC_Payment_Gateway_WCPay $gateway WooCommerce Payments gateway.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Account $account, WC_Payment_Gateway_WCPay $gateway ) {
		$this->domain_name             = wp_parse_url( get_site_url(), PHP_URL_HOST );
		$this->apple_pay_verify_notice = '';
		$this->payments_api_client     = $payments_api_client;
		$this->account                 = $account;
		$this->gateway                 = $gateway;
	}

	/**
	 * Initializes this class's hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'init', [ $this, 'add_domain_association_rewrite_rule' ], 5 );
		add_action( 'woocommerce_woocommerce_payments_updated', [ $this, 'verify_domain_on_update' ] );
		add_action( 'init', [ $this, 'init' ] );
	}

	/**
	 * Initialize hooks.
	 *
	 * @return  void
	 */
	public function init() {
		add_action( 'admin_init', [ $this, 'verify_domain_on_domain_name_change' ] );
		add_filter( 'query_vars', [ $this, 'whitelist_domain_association_query_param' ], 10, 1 );
		add_action( 'parse_request', [ $this, 'parse_domain_association_request' ], 10, 1 );

		add_action( 'woocommerce_woocommerce_payments_admin_notices', [ $this, 'display_error_notice' ] );
		add_action( 'add_option_woocommerce_woocommerce_payments_settings', [ $this, 'verify_domain_on_new_settings' ], 10, 2 );
		add_action( 'update_option_woocommerce_woocommerce_payments_settings', [ $this, 'verify_domain_on_updated_settings' ], 10, 2 );
	}

	/**
	 * Whether the gateway and Express Checkout Buttons (prerequisites for Apple Pay) are enabled.
	 *
	 * @return bool Whether Apple Pay required settings are enabled.
	 */
	private function is_enabled() {
		return $this->gateway->is_enabled() && 'yes' === $this->gateway->get_option( 'payment_request' );
	}

	/**
	 * Whether the gateway and Express Checkout Buttons were enabled in previous settings.
	 *
	 * @param array|null $prev_settings Gateway settings.
	 *
	 * @return bool Whether Apple Pay required settings are enabled.
	 */
	private function was_enabled( $prev_settings ) {
		$gateway_enabled         = 'yes' === ( $prev_settings['enabled'] ?? 'no' );
		$payment_request_enabled = 'yes' === ( $prev_settings['payment_request'] ?? 'no' );
		return $gateway_enabled && $payment_request_enabled;
	}

	/**
	 * Trigger Apple Pay registration upon domain name change.
	 */
	public function verify_domain_on_domain_name_change() {
		$verified_domain = $this->gateway->get_option( 'apple_pay_verified_domain' );
		if ( $this->domain_name !== $verified_domain ) {
			$this->verify_domain_if_configured();
		}
	}

	/**
	 * Verify domain upon plugin update only in case the domain association file has changed.
	 */
	public function verify_domain_on_update() {
		if ( $this->is_enabled() && ! $this->is_hosted_domain_association_file_up_to_date() ) {
			$this->verify_domain_if_configured();
		}
	}

	/**
	 * Vefifies if hosted domain association file is up to date
	 * with the file from the plugin directory.
	 *
	 * @return bool Whether file is up to date or not.
	 */
	private function is_hosted_domain_association_file_up_to_date() {
		$fullpath = untrailingslashit( ABSPATH ) . '/' . self::DOMAIN_ASSOCIATION_FILE_DIR . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME;
		if ( ! file_exists( $fullpath ) ) {
			return false;
		}
		// Contents of domain association file from plugin dir.
		$new_contents = @file_get_contents( WCPAY_ABSPATH . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME ); // @codingStandardsIgnoreLine
		// Get file contents from local path and remote URL and check if either of which matches.
		$local_contents  = @file_get_contents( $fullpath ); // @codingStandardsIgnoreLine
		$url             = get_site_url() . '/' . self::DOMAIN_ASSOCIATION_FILE_DIR . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME;
		$response        = @wp_remote_get( $url ); // @codingStandardsIgnoreLine
		$remote_contents = @wp_remote_retrieve_body( $response ); // @codingStandardsIgnoreLine

		return $local_contents === $new_contents || $remote_contents === $new_contents;
	}

	/**
	 * Copies and overwrites domain association file.
	 *
	 * @return null|string Error message.
	 */
	private function copy_and_overwrite_domain_association_file() {
		$well_known_dir = untrailingslashit( ABSPATH ) . '/' . self::DOMAIN_ASSOCIATION_FILE_DIR;
		$fullpath       = $well_known_dir . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME;

		if ( ! is_dir( $well_known_dir ) && ! @mkdir( $well_known_dir, 0755 ) && ! is_dir( $well_known_dir ) ) { // @codingStandardsIgnoreLine
			return __( 'Unable to create domain association folder to domain root.', 'woocommerce-payments' );
		}

		if ( ! @copy( WCPAY_ABSPATH . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME, $fullpath ) ) { // @codingStandardsIgnoreLine
			return __( 'Unable to copy domain association file to domain root.', 'woocommerce-payments' );
		}
	}

	/**
	 * Updates the Apple Pay domain association file.
	 * Reports failure only if file isn't already being served properly.
	 */
	public function update_domain_association_file() {
		if ( $this->is_hosted_domain_association_file_up_to_date() ) {
			return;
		}

		$error_message = $this->copy_and_overwrite_domain_association_file();

		if ( isset( $error_message ) ) {
			$url = get_site_url() . '/' . self::DOMAIN_ASSOCIATION_FILE_DIR . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME;
			Logger::log(
				'Error: ' . $error_message . ' ' .
				/* translators: expected domain association file URL */
				sprintf( __( 'To enable Apple Pay, domain association file must be hosted at %s.', 'woocommerce-payments' ), $url )
			);
		} else {
			Logger::log( __( 'Domain association file updated.', 'woocommerce-payments' ) );
		}
	}

	/**
	 * Adds a rewrite rule for serving the domain association file from the proper location.
	 */
	public function add_domain_association_rewrite_rule() {
		$regex    = '^\\' . self::DOMAIN_ASSOCIATION_FILE_DIR . '\/' . self::DOMAIN_ASSOCIATION_FILE_NAME . '$';
		$redirect = 'index.php?' . self::DOMAIN_ASSOCIATION_FILE_NAME . '=1';

		add_rewrite_rule( $regex, $redirect, 'top' );
	}

	/**
	 * Add to the list of publicly allowed query variables.
	 *
	 * @param  array $query_vars - provided public query vars.
	 * @return array Updated public query vars.
	 */
	public function whitelist_domain_association_query_param( $query_vars ) {
		$query_vars[] = self::DOMAIN_ASSOCIATION_FILE_NAME;
		return $query_vars;
	}

	/**
	 * Serve domain association file when proper query param is provided.
	 *
	 * @param object $wp WordPress environment object.
	 */
	public function parse_domain_association_request( $wp ) {
		if (
			! isset( $wp->query_vars[ self::DOMAIN_ASSOCIATION_FILE_NAME ] ) ||
			'1' !== $wp->query_vars[ self::DOMAIN_ASSOCIATION_FILE_NAME ]
		) {
			return;
		}

		$path = WCPAY_ABSPATH . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME;
		header( 'Content-Type: text/plain;charset=utf-8' );
		echo esc_html( @file_get_contents( $path ) ); // @codingStandardsIgnoreLine
		exit;
	}

	/**
	 * Returns the string representation of the current mode. One of:
	 *   - 'dev'
	 *   - 'test'
	 *   - 'live'
	 *
	 * @return string A string representation of the current mode.
	 */
	private function get_gateway_mode_string() {
		if ( WC_Payments::mode()->is_dev() ) {
			return 'dev';
		} elseif ( WC_Payments::mode()->is_test() ) {
			return 'test';
		}
		return 'live';
	}



	/**
	 * Processes the Stripe domain registration.
	 */
	public function register_domain() {
		$error = null;

		try {
			$registration_response = $this->payments_api_client->register_domain( $this->domain_name );

			if ( isset( $registration_response['id'] ) && ( isset( $registration_response['apple_pay']['status'] ) && 'active' === $registration_response['apple_pay']['status'] ) ) {
				$this->gateway->update_option( 'apple_pay_verified_domain', $this->domain_name );
				$this->gateway->update_option( 'apple_pay_domain_set', 'yes' );

				Logger::log( __( 'Your domain has been verified with Apple Pay!', 'woocommerce-payments' ) );
				Tracker::track_admin(
					'wcpay_apple_pay_domain_registration_success',
					[
						'domain' => $this->domain_name,
						'mode'   => $this->get_gateway_mode_string(),
					]
				);

				return;
			} elseif ( isset( $registration_response['apple_pay']['status_details']['error_message'] ) ) {
				$error = $registration_response['apple_pay']['status_details']['error_message'];
			}
		} catch ( API_Exception $e ) {
			$error = $e->getMessage();
		}
		// Display error message in notice.
		$this->apple_pay_verify_notice = $error;

		$this->gateway->update_option( 'apple_pay_verified_domain', $this->domain_name );
		$this->gateway->update_option( 'apple_pay_domain_set', 'no' );

		Logger::log( 'Error registering domain with Apple: ' . $error );
		Tracker::track_admin(
			'wcpay_apple_pay_domain_registration_failure',
			[
				'domain' => $this->domain_name,
				'reason' => $error,
				'mode'   => $this->get_gateway_mode_string(),
			]
		);
	}

	/**
	 * Process the Apple Pay domain verification if proper settings are configured.
	 */
	public function verify_domain_if_configured() {
		// If Express Checkout Buttons are not enabled,
		// do not attempt to register domain.
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Ensure that domain association file will be served.
		flush_rewrite_rules();

		// The rewrite rule method doesn't work if permalinks are set to Plain.
		// Create/update domain association file by copying it from the plugin folder as a fallback.
		$this->update_domain_association_file();

		// Register the domain.
		$this->register_domain();
	}

	/**
	 * Conditionally process the Apple Pay domain verification after settings are initially set.
	 *
	 * @param string $option   Option name.
	 * @param array  $settings Settings array.
	 */
	public function verify_domain_on_new_settings( $option, $settings ) {
		$this->verify_domain_on_updated_settings( [], $settings );
	}

	/**
	 * Conditionally process the Apple Pay domain verification after settings are updated.
	 *
	 * @param array $prev_settings Settings before update.
	 * @param array $settings      Settings after update.
	 */
	public function verify_domain_on_updated_settings( $prev_settings, $settings ) {
		// If Gateway or Express Checkout Buttons weren't enabled, then might need to verify now.
		if ( ! $this->was_enabled( $prev_settings ) ) {
			$this->verify_domain_if_configured();
		}
	}

	/**
	 * Display Apple Pay registration errors.
	 */
	public function display_error_notice() {
		if ( ! $this->is_enabled() || ! $this->account->get_is_live() ) {
			return;
		}

		$empty_notice = empty( $this->apple_pay_verify_notice );
		$domain_set   = $this->gateway->get_option( 'apple_pay_domain_set' );
		// Don't display error notice if verification notice is empty and
		// apple_pay_domain_set option equals to '' or 'yes'.
		if ( $empty_notice && 'no' !== $domain_set ) {
			return;
		}

		/**
		 * Apple pay is enabled by default and domain verification initializes
		 * when setting screen is displayed. So if domain verification is not set,
		 * something went wrong so lets notify user.
		 */
		$allowed_html                      = [
			'a' => [
				'href'  => [],
				'title' => [],
			],
		];
		$payment_request_button_text       = __( 'Express checkouts:', 'woocommerce-payments' );
		$verification_failed_without_error = __( 'Apple Pay domain verification failed.', 'woocommerce-payments' );
		$verification_failed_with_error    = __( 'Apple Pay domain verification failed with the following error:', 'woocommerce-payments' );
		$check_log_text                    = WC_Payments_Utils::esc_interpolated_html(
			/* translators: a: Link to the logs page */
			__( 'Please check the <a>logs</a> for more details on this issue. Debug log must be enabled under <strong>Advanced settings</strong> to see recorded logs.', 'woocommerce-payments' ),
			[
				'a'      => '<a href="' . admin_url( 'admin.php?page=wc-status&tab=logs' ) . '">',
				'strong' => '<strong>',
			]
		);
		$learn_more_text = WC_Payments_Utils::esc_interpolated_html(
			__( '<a>Learn more</a>.', 'woocommerce-payments' ),
			[
				'a' => '<a href="https://woocommerce.com/document/woopayments/payment-methods/apple-pay/#domain-registration" target="_blank">',
			]
		);

		?>
		<div class="notice notice-error apple-pay-message">
			<?php if ( $empty_notice ) : ?>
				<p>
					<strong><?php echo esc_html( $payment_request_button_text ); ?></strong>
					<?php echo esc_html( $verification_failed_without_error ); ?>
					<?php echo $learn_more_text; /* @codingStandardsIgnoreLine */ ?>
				</p>
<?php else : ?>
				<p>
					<strong><?php echo esc_html( $payment_request_button_text ); ?></strong>
					<?php echo esc_html( $verification_failed_with_error ); ?>
					<?php echo $learn_more_text; /* @codingStandardsIgnoreLine */ ?>
				</p>
				<p><i><?php echo wp_kses( make_clickable( esc_html( $this->apple_pay_verify_notice ) ), $allowed_html ); ?></i></p>
<?php endif; ?>
			<p><?php echo $check_log_text; /* @codingStandardsIgnoreLine */ ?></p>
		</div>
		<?php
	}
}
