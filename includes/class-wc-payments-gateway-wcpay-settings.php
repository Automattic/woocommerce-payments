<?php
/**
 * Class WC_Payments_Gateway_WCPay_Settings.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gateway settings class for WooCommerce Payments.
 */
class WC_Payments_Gateway_WCPay_Settings {

	/**
	 * Returns the setting form for WC Payments.
	 *
	 * @return array[] of settings fields.
	 */
	public function get_form_fields() {
		return [
			'enabled'                          => [
				'title'       => __( 'Enable/disable', 'woocommerce-payments' ),
				'label'       => __( 'Enable WooCommerce Payments', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
			'account_statement_descriptor'     => [
				'type'        => 'account_statement_descriptor',
				'title'       => __( 'Customer bank statement', 'woocommerce-payments' ),
				'description' => WC_Payments_Utils::esc_interpolated_html(
					__( 'Edit the way your store name appears on your customers’ bank statements (read more about requirements <a>here</a>).', 'woocommerce-payments' ),
					[ 'a' => '<a href="https://woocommerce.com/document/payments/bank-statement-descriptor/" target="_blank" rel="noopener noreferrer">' ]
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
		];
	}

}
