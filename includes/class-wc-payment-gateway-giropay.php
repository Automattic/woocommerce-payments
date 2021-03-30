<?php
/**
 * Class WC_Payment_Gateway_Giropay
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gateway class for WooCommerce Payments
 */
class WC_Payment_Gateway_Giropay extends WC_Payment_Gateway_CC {
	public $id = 'woocommerce_payments_giropay';
	public $icon = '';
	public $has_fields = false;
	public $method_title = 'WooCommerce Payments';
	public $method_description = 'giropay';
	public $title = 'giropay';

	public function __construct() {
		$this->init_form_fields();
		$this->init_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function process_payment( $order_id ) {
		WC()->cart->empty_cart();

		return array(
			'result' => 'success',
			'redirect' => $this->get_return_url()
		);
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => __( 'Enable giropay Payment', 'woocommerce' ),
				'default' => 'yes'
			),
		);
	}
}
