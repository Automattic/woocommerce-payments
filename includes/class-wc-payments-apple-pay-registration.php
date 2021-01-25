<?php
/**
 * Stripe Apple Pay Registration Class.
 *
 * Adapted from WooCommerce Stripe Gateway extension.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WCPay\Logger;

/**
 * WC_Payments_Apple_Pay_Registration class.
 */
class WC_Payments_Apple_Pay_Registration {
	/**
	 * Gateway settings.
	 *
	 * @var array
	 */
	public $gateway_settings;

	/**
	 * Apple Pay Domain Set.
	 *
	 * @var bool
	 */
	public $apple_pay_domain_set;

	/**
	 * Stores Apple Pay domain verification issues.
	 *
	 * @var string
	 */
	public $apple_pay_verify_notice;

	/**
	 * Initialize class actions.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'add_domain_association_rewrite_rule' ] );
		add_filter( 'query_vars', [ $this, 'whitelist_domain_association_query_param' ], 10, 1 );
		add_action( 'parse_request', [ $this, 'parse_domain_association_request' ], 10, 1 );
		add_action( 'woocommerce_woocommerce_payments_updated', [ $this, 'verify_domain_if_configured' ] );

		add_action( 'add_option_woocommerce_woocommerce_payments_settings', [ $this, 'verify_domain_on_new_settings' ], 10, 2 );
		add_action( 'update_option_woocommerce_woocommerce_payments_settings', [ $this, 'verify_domain_on_updated_settings' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );

		$this->gateway_settings        = get_option( 'woocommerce_woocommerce_payments_settings', [] );
		$this->apple_pay_domain_set    = 'yes' === $this->get_option( 'apple_pay_domain_set', 'no' );
		$this->apple_pay_verify_notice = '';
	}

	/**
	 * Gets the Gateway settings.
	 *
	 * @param string $setting Setting key.
	 * @param string $default Default value.
	 * @return string $setting_value Setting value or default value.
	 */
	public function get_option( $setting = '', $default = '' ) {
		if ( empty( $this->gateway_settings ) ) {
			return $default;
		}

		if ( ! empty( $this->gateway_settings[ $setting ] ) ) {
			return $this->gateway_settings[ $setting ];
		}

		return $default;
	}

	/**
	 * Whether the gateway and Payment Request Button (prerequisites for Apple Pay) are enabled.
	 *
	 * @return string Whether Apple Pay required settings are enabled.
	 */
	private function is_enabled() {
		$gateway_enabled         = 'yes' === $this->get_option( 'enabled', 'no' );
		$payment_request_enabled = 'yes' === $this->get_option( 'payment_request', 'yes' );

		return $gateway_enabled && $payment_request_enabled;
	}

	/**
	 * Updates the Apple Pay domain association file.
	 * Reports failure only if file isn't already being served properly.
	 */
	public function update_domain_association_file() {
		$path     = untrailingslashit( ABSPATH );
		$dir      = '.well-known';
		$file     = 'apple-developer-merchantid-domain-association';
		$fullpath = $path . '/' . $dir . '/' . $file;

		$existing_contents = @file_get_contents( $fullpath ); // @codingStandardsIgnoreLine
		$new_contents      = @file_get_contents( WCPAY_ABSPATH . '/' . $file ); // @codingStandardsIgnoreLine
		if ( $existing_contents === $new_contents ) {
			return;
		}

		$error = null;
		if ( ! file_exists( $path . '/' . $dir ) ) {
			if ( ! @mkdir( $path . '/' . $dir, 0755 ) ) { // @codingStandardsIgnoreLine
				$error = __( 'Unable to create domain association folder to domain root.', 'woocommerce-payments' );
			}
		}

		if ( ! @copy( WCPAY_ABSPATH . '/' . $file, $fullpath ) ) { // @codingStandardsIgnoreLine
			$error = __( 'Unable to copy domain association file to domain root.', 'woocommerce-payments' );
		}

		if ( isset( $error ) ) {
			$url            = get_site_url() . '/' . $dir . '/' . $file;
			$response       = @wp_remote_get( $url ); // @codingStandardsIgnoreLine
			$already_hosted = @wp_remote_retrieve_body( $response ) === $new_contents; // @codingStandardsIgnoreLine
			if ( ! $already_hosted ) {
				Logger::log(
					'Error: ' . $error . ' ' .
					/* translators: expected domain association file URL */
					sprintf( __( 'To enable Apple Pay, domain association file must be hosted at %s.', 'woocommerce-payments' ), $url )
				);
			}
		}

		Logger::log( 'Domain association file updated.' );
	}

	/**
	 * Gets the Stripe secret key for the current mode.
	 *
	 * @return string Secret key.
	 */
	private function get_secret_key() {
		$testmode = 'yes' === $this->get_option( 'testmode', 'no' );
		return $testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
	}

	/**
	 * Adds a rewrite rule for serving the domain association file from the proper location.
	 */
	public function add_domain_association_rewrite_rule() {
		$regex    = '^\.well-known\/apple-developer-merchantid-domain-association$';
		$redirect = 'index.php?apple-developer-merchantid-domain-association=1';

		add_rewrite_rule( $regex, $redirect, 'top' );
	}

	/**
	 * Add to the list of publicly allowed query variables.
	 *
	 * @param  array $query_vars - provided public query vars.
	 * @return array Updated public query vars.
	 */
	public function whitelist_domain_association_query_param( $query_vars ) {
		$query_vars[] = 'apple-developer-merchantid-domain-association';
		return $query_vars;
	}

	/**
	 * Serve domain association file when proper query param is provided.
	 *
	 * @param object $wp WordPress environment object.
	 */
	public function parse_domain_association_request( $wp ) {
		if (
			! isset( $wp->query_vars['apple-developer-merchantid-domain-association'] ) ||
			'1' !== $wp->query_vars['apple-developer-merchantid-domain-association']
		) {
			return;
		}

		$path = WCPAY_ABSPATH . '/apple-developer-merchantid-domain-association';
		header( 'Content-Type: application/octet-stream' );
		echo esc_html( wp_remote_get( $path ) );
		exit;
	}

	/**
	 * Makes request to register the domain with Stripe/Apple Pay.
	 *
	 * @param string $secret_key
	 * @throws Exception
	 */
	private function make_domain_registration_request( $secret_key ) {
		if ( empty( $secret_key ) ) {
			throw new Exception( __( 'Unable to verify domain - missing secret key.', 'woocommerce-payments' ) );
		}

		$endpoint = 'https://api.stripe.com/v1/apple_pay/domains';

		$data = [
			'domain_name' => $_SERVER['HTTP_HOST'],
		];

		$headers = [
			'User-Agent'    => 'WooCommerce Stripe Apple Pay',
			'Authorization' => 'Bearer ' . $secret_key,
		];

		$response = wp_remote_post(
			$endpoint,
			[
				'headers' => $headers,
				'body'    => http_build_query( $data ),
			]
		);

		if ( is_wp_error( $response ) ) {
			/* translators: error message */
			throw new Exception( sprintf( __( 'Unable to verify domain - %s', 'woocommerce-payments' ), $response->get_error_message() ) );
		}

		if ( 200 !== $response['response']['code'] ) {
			$parsed_response = json_decode( $response['body'] );

			$this->apple_pay_verify_notice = $parsed_response->error->message;

			/* translators: error message */
			throw new Exception( sprintf( __( 'Unable to verify domain - %s', 'woocommerce-payments' ), $parsed_response->error->message ) );
		}
	}

	/**
	 * Processes the Apple Pay domain verification.
	 *
	 * @param string $secret_key
	 * @return bool Whether domain verification succeeded.
	 */
	public function register_domain_with_apple( $secret_key ) {
		try {
			$this->make_domain_registration_request( $secret_key );

			// No errors to this point, verification success!
			$this->gateway_settings['apple_pay_domain_set'] = 'yes';
			$this->apple_pay_domain_set                     = true;

			update_option( 'woocommerce_woocommerce_payments_settings', $this->gateway_settings );

			Logger::log( 'Your domain has been verified with Apple Pay!' );

			return true;

		} catch ( Exception $e ) {
			$this->gateway_settings['apple_pay_domain_set'] = 'no';
			$this->apple_pay_domain_set                     = false;

			update_option( 'woocommerce_woocommerce_payments_settings', $this->gateway_settings );

			Logger::log( 'Error: ' . $e->getMessage() );

			return false;
		}
	}

	/**
	 * Process the Apple Pay domain verification if proper settings are configured.
	 */
	public function verify_domain_if_configured() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Ensure that domain association file will be served.
		flush_rewrite_rules();

		// The rewrite rule method doesn't work if permalinks are set to Plain.
		// Create/update domain association file by copying it from the plugin folder as a fallback.
		$this->update_domain_association_file();

		// Register the domain with Apple Pay.
		// $verification_complete = $this->register_domain_with_apple( $secret_key );

		// Show/hide notes if necessary.
		// WC_Stripe_Inbox_Notes::notify_on_apple_pay_domain_verification( $verification_complete );
	}

	/**
	 * Conditionally process the Apple Pay domain verification after settings are initially set.
	 *
	 * @param object $option
	 * @param array  $settings
	 */
	public function verify_domain_on_new_settings( $option, $settings ) {
		$this->verify_domain_on_updated_settings( [], $settings );
	}

	/**
	 * Conditionally process the Apple Pay domain verification after settings are updated.
	 */
	public function verify_domain_on_updated_settings( $prev_settings, $settings ) {
		// Grab previous state and then update cached settings.
		$this->gateway_settings = $prev_settings;
		$prev_secret_key        = $this->get_secret_key();
		$prev_is_enabled        = $this->is_enabled();
		$this->gateway_settings = $settings;

		// If Stripe or Payment Request Button wasn't enabled (or secret key was different) then might need to verify now.
		if ( ! $prev_is_enabled || ( $this->get_secret_key() !== $prev_secret_key ) ) {
			$this->verify_domain_if_configured();
		}
	}

	/**
	 * Display any admin notices to the user.
	 */
	public function admin_notices() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$empty_notice = empty( $this->apple_pay_verify_notice );
		if ( $empty_notice && ( $this->apple_pay_domain_set || empty( $this->secret_key ) ) ) {
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
		$verification_failed_without_error = __( 'Apple Pay domain verification failed.', 'woocommerce-payments' );
		$verification_failed_with_error    = __( 'Apple Pay domain verification failed with the following error:', 'woocommerce-payments' );
		$check_log_text                    = sprintf(
			/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
			esc_html__( 'Please check the %1$slogs%2$s for more details on this issue. Logging must be enabled to see recorded logs.', 'woocommerce-payments' ),
			'<a href="' . admin_url( 'admin.php?page=wc-status&tab=logs' ) . '">',
			'</a>'
		);

		?>
		<div class="error stripe-apple-pay-message">
			<?php if ( $empty_notice ) : ?>
				<p><?php echo esc_html( $verification_failed_without_error ); ?></p>
			<?php else : ?>
				<p><?php echo esc_html( $verification_failed_with_error ); ?></p>
				<p><i><?php echo wp_kses( make_clickable( esc_html( $this->apple_pay_verify_notice ) ), $allowed_html ); ?></i></p>
			<?php endif; ?>
			<p><?php echo $check_log_text; ?></p>
		</div>
		<?php
	}
}

new WC_Payments_Apple_Pay_Registration();
