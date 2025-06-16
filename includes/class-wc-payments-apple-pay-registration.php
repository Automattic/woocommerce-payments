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
		add_action( 'init', [ $this, 'init' ] );
	}

	/**
	 * Initialize hooks.
	 *
	 * @return  void
	 */
	public function init() {
		add_action( 'admin_init', [ $this, 'verify_domain_on_domain_name_change' ] );

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
