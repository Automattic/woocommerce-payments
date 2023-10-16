<?php
/**
 * Class GatewaySettingsService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Payment_Gateway_WCPay;
use WC_Payments_Utils;

/**
 * Service for retrieving and managing gateway settings.
 */
class GatewaySettingsService {
	/**
	 * Storage for form fields, which should be generated only once.
	 *
	 * @var array
	 */
	private $form_fields;

	/**
	 * Returns all form fields for the gateway.
	 *
	 * @return array
	 */
	public function get_form_fields(): array {
		if ( isset( $this->form_fields ) ) {
			return $this->form_fields;
		}

		$this->form_fields = [
			'enabled'                          => [
				'title'       => __( 'Enable/disable', 'woocommerce-payments' ),
				'label'       => sprintf(
					/* translators: %s: WooPayments */
					__( 'Enable %s', 'woocommerce-payments' ),
					'WooPayments'
				),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
			'account_statement_descriptor'     => [
				'type'        => 'account_statement_descriptor',
				'title'       => __( 'Customer bank statement', 'woocommerce-payments' ),
				'description' => WC_Payments_Utils::esc_interpolated_html(
					__( 'Edit the way your store name appears on your customers’ bank statements (read more about requirements <a>here</a>).', 'woocommerce-payments' ),
					[ 'a' => '<a href="https://woocommerce.com/document/woopayments/customization-and-translation/bank-statement-descriptor/" target="_blank" rel="noopener noreferrer">' ]
				),
			],
			'manual_capture'                   => [
				'title'       => __( 'Manual capture', 'woocommerce-payments' ),
				'label'       => __( 'Issue an authorization on checkout, and capture later.', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Charge must be captured within 7 days of authorization, otherwise the authorization and order will be canceled.', 'woocommerce-payments' ),
				'default'     => 'no',
			],
			'saved_cards'                      => [
				'title'       => __( 'Saved cards', 'woocommerce-payments' ),
				'label'       => __( 'Enable payment via saved cards', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on our platform, not on your store.', 'woocommerce-payments' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			],
			'test_mode'                        => [
				'title'       => __( 'Test mode', 'woocommerce-payments' ),
				'label'       => __( 'Enable test mode', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Simulate transactions using test card numbers.', 'woocommerce-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
			],
			'enable_logging'                   => [
				'title'       => __( 'Debug log', 'woocommerce-payments' ),
				'label'       => __( 'When enabled debug notes will be added to the log.', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
			'payment_request_details'          => [
				'title'       => __( 'Payment request buttons', 'woocommerce-payments' ),
				'type'        => 'title',
				'description' => '',
			],
			'payment_request'                  => [
				'title'       => __( 'Enable/disable', 'woocommerce-payments' ),
				'label'       => sprintf(
					/* translators: 1) br tag 2) Stripe anchor tag 3) Apple anchor tag */
					__( 'Enable payment request buttons (Apple Pay, Google Pay, and more). %1$sBy using Apple Pay, you agree to %2$s and %3$s\'s Terms of Service.', 'woocommerce-payments' ),
					'<br />',
					'<a href="https://stripe.com/apple-pay/legal" target="_blank">Stripe</a>',
					'<a href="https://developer.apple.com/apple-pay/acceptable-use-guidelines-for-websites/" target="_blank">Apple</a>'
				),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay using Apple Pay, Google Pay or the Payment Request API if supported by the browser.', 'woocommerce-payments' ),
				'default'     => empty( get_option( 'woocommerce_woocommerce_payments_settings' ) ) ? 'yes' : 'no', // Enable by default for new installations only.
				'desc_tip'    => true,
			],
			'payment_request_button_type'      => [
				'title'       => __( 'Button type', 'woocommerce-payments' ),
				'type'        => 'select',
				'description' => __( 'Select the button type you would like to show.', 'woocommerce-payments' ),
				'default'     => 'buy',
				'desc_tip'    => true,
				'options'     => [
					'default' => __( 'Only icon', 'woocommerce-payments' ),
					'buy'     => __( 'Buy', 'woocommerce-payments' ),
					'donate'  => __( 'Donate', 'woocommerce-payments' ),
					'book'    => __( 'Book', 'woocommerce-payments' ),
				],
			],
			'payment_request_button_theme'     => [
				'title'       => __( 'Button theme', 'woocommerce-payments' ),
				'type'        => 'select',
				'description' => __( 'Select the button theme you would like to show.', 'woocommerce-payments' ),
				'default'     => 'dark',
				'desc_tip'    => true,
				'options'     => [
					'dark'          => __( 'Dark', 'woocommerce-payments' ),
					'light'         => __( 'Light', 'woocommerce-payments' ),
					'light-outline' => __( 'Light-Outline', 'woocommerce-payments' ),
				],
			],
			'payment_request_button_height'    => [
				'title'       => __( 'Button height', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Enter the height you would like the button to be in pixels. Width will always be 100%.', 'woocommerce-payments' ),
				'default'     => '44',
				'desc_tip'    => true,
			],
			'payment_request_button_label'     => [
				'title'       => __( 'Custom button label', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Enter the custom text you would like the button to have.', 'woocommerce-payments' ),
				'default'     => __( 'Buy now', 'woocommerce-payments' ),
				'desc_tip'    => true,
			],
			'payment_request_button_locations' => [
				'title'             => __( 'Button locations', 'woocommerce-payments' ),
				'type'              => 'multiselect',
				'description'       => __( 'Select where you would like to display the button.', 'woocommerce-payments' ),
				'default'           => [
					'product',
					'cart',
					'checkout',
				],
				'class'             => 'wc-enhanced-select',
				'desc_tip'          => true,
				'options'           => [
					'product'  => __( 'Product', 'woocommerce-payments' ),
					'cart'     => __( 'Cart', 'woocommerce-payments' ),
					'checkout' => __( 'Checkout', 'woocommerce-payments' ),
				],
				'custom_attributes' => [
					'data-placeholder' => __( 'Select pages', 'woocommerce-payments' ),
				],
			],
			'upe_enabled_payment_method_ids'   => [
				'title'   => __( 'Payments accepted on checkout', 'woocommerce-payments' ),
				'type'    => 'multiselect',
				'default' => [ 'card' ],
				'options' => [],
			],
			'payment_request_button_size'      => [
				'title'       => __( 'Size of the button displayed for Express Checkouts', 'woocommerce-payments' ),
				'type'        => 'select',
				'description' => __( 'Select the size of the button.', 'woocommerce-payments' ),
				'default'     => 'default',
				'desc_tip'    => true,
				'options'     => [
					'default' => __( 'Default', 'woocommerce-payments' ),
					'medium'  => __( 'Medium', 'woocommerce-payments' ),
					'large'   => __( 'Large', 'woocommerce-payments' ),
				],
			],
			'platform_checkout_custom_message' => [ 'default' => __( 'By placing this order, you agree to our [terms_of_service_link] and understand our [privacy_policy_link].', 'woocommerce-payments' ) ],
		];

		return $this->form_fields;
	}

	/**
	 * Checks if the gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return 'yes' === $this->get( 'enabled' );
	}

	/**
	 * Returns the account statement descriptor or an empty string.
	 *
	 * @return string
	 */
	public function get_account_statement_descriptor(): string {
		return $this->get( 'account_statement_descriptor' ) ?? '';
	}

	/**
	 * Checks if manual capture is enabled.
	 *
	 * @return bool
	 */
	public function is_manual_capture_enabled(): bool {
		return 'yes' === $this->get( 'manual_capture' );
	}

	/**
	 * Checks if saved cards are enabled.
	 *
	 * @return bool
	 */
	public function is_saved_cards_enabled(): bool {
		return 'yes' === $this->get( 'saved_cards' );
	}

	/**
	 * Checks if test mode is toggled.
	 *
	 * @return bool
	 * @see WCPay\Core\Mode manages the working mode, not this setting.
	 */
	public function is_test_mode_enabled(): bool {
		return 'yes' === $this->get( 'test_mode' );
	}

	/**
	 * Checks if logging is enabled.
	 *
	 * @return bool
	 */
	public function is_logging_enabled(): bool {
		return 'yes' === $this->get( 'logging_enabled' );
	}

	/**
	 * Checks if payment requests are enabled.
	 *
	 * @return bool
	 */
	public function is_payment_request_enabled(): bool {
		return 'yes' === $this->get( 'payment_request' );
	}

	/**
	 * Returns the type of payment request buttons.
	 *
	 * @return string
	 */
	public function get_payment_request_button_type(): string {
		return $this->get( 'payment_request_button_type' );
	}

	/**
	 * Returns the theme of the payment rquest button.
	 *
	 * @return string
	 */
	public function get_payment_request_button_theme(): string {
		return $this->get( 'payment_request_button_theme' );
	}

	/**
	 * Returns the height of payment request buttons.
	 *
	 * @return int
	 */
	public function get_payment_request_button_height(): int {
		return intval( $this->get( 'payment_request_button_height' ) );
	}

	/**
	 * Returns the label for payment request buttons.
	 *
	 * @return string
	 */
	public function get_payment_request_button_label(): string {
		return $this->get( 'payment_request_button_label' );
	}

	/**
	 * Returns the locations where payment request buttons are enabled.
	 *
	 * @return string[]
	 */
	public function get_payment_request_button_locations(): array {
		return $this->get( 'payment_request_button_locations' );
	}

	/**
	 * Returns the size of payment request buttons.
	 *
	 * @return string
	 */
	public function get_payment_request_button_size(): string {
		return $this->get( 'payment_request_button_size' );
	}

	/**
	 * Returns the platform checkout message.
	 *
	 * @return string
	 */
	public function get_platform_checkout_custom_message(): string {
		return $this->get( 'platform_checkout_custom_message' );
	}

	/**
	 * Returns the IDs of enabled UPE payment methods.
	 *
	 * @return string[]
	 */
	public function get_upe_enabled_payment_method_ids(): array {
		return $this->get( 'upe_enabled_payment_method_ids' );
	}

	/**
	 * Retrieves a setting.
	 *
	 * @param string $key Key for the setting.
	 * @return mixed      Value for the setting, or the default.
	 */
	private function get( string $key ) {
		// Get fresh options every time.
		$options_key = sprintf( 'woocommerce_%s_settings', WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$all_options = get_option( $options_key, null );

		// If the option exists, use it.
		if ( isset( $all_options[ $key ] ) ) {
			return $all_options[ $key ];
		}

		// Fall back to a default, if available.
		$form_fields = $this->get_form_fields();
		if ( isset( $form_fields[ $key ] ) && isset( $form_fields[ $key ]['default'] ) ) {
			return $form_fields[ $key ]['default'];
		}

		return null;
	}
}
