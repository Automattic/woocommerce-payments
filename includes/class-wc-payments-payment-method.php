<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Payments_Payment_Method extends AbstractPaymentMethodType {
	protected $name = 'woocommerce_payments';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_woocommerce_payments_settings', [] );
	}

	public function is_active() {
		return WC_Payments::get_gateway()->is_available();
	}

	public function get_payment_method_script_handles() {
		wp_register_script( 'wcpay-stripe', 'https://js.stripe.com/v3/' );
		wp_register_script( 'wcpay-stripe-elements', 'https://unpkg.com/@stripe/react-stripe-js@latest/dist/react-stripe.umd.js' );

		wp_register_script(
			'wc-payment-method-wcpay',
			plugins_url( 'dist/blocks-checkout.js', WCPAY_PLUGIN_FILE ),
			array( 'wcpay-stripe', 'wcpay-stripe-elements' ),
			'1.0.1',
			true
		);

		$config = WC_Payments::get_gateway()->get_payment_fields_js_config();
		wp_localize_script( 'wc-payment-method-wcpay', 'wcpay_config', $config );

		return [ 'wc-payment-method-wcpay' ];
	}

	public function get_payment_method_data() {
		return [
			'title'       => isset( $this->settings['title'] ) ? $this->settings['title'] : '',
			'description' => isset( $this->settings['description'] ) ? $this->settings['description'] : '',
		];
	}
}
