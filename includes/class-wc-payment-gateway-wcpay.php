<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Payment_Gateway_WCPay extends WC_Payment_Gateway_CC {

	const GATEWAY_ID = 'woocommerce_payments';

	public function __construct() {
		$this->id                 = self::GATEWAY_ID;
		$this->icon               = ''; // TODO: icon
		$this->has_fields         = true;
		$this->method_title       = __( 'WooCommerce Payments', 'woocommerce-payments' );
		$this->method_description = __( 'Accept payments via a WooCommerce-branded payment gateway', 'woocommerce-payments' );

		$this->form_fields = array(
			'enabled'         => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-payments' ),
				'label'       => __( 'Enable WooCommerce Payments', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'           => array(
				'title'       => __( 'Title', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-payments' ),
				'default'     => __( 'Credit Card (WooCommerce Payments)', 'woocommerce-payments' ),
				'desc_tip'    => true,
			),
			'description'     => array(
				'title'       => __( 'Description', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-payments' ),
				'default'     => __( 'Pay with your credit card via WooCommerce Payments.', 'woocommerce-payments' ),
				'desc_tip'    => true,
			),
		);
		$this->init_settings();

		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function payment_fields() {
		echo $this->get_description();
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$amount = $order->get_total();

		if ( $amount > 0 ) {
			// TODO: implement the actual payment (that's the easy part, right?)
			$transaction_id = 'my-groovy-transaction-id';

			$order->add_order_note( sprintf( __( 'A payment of %s was successfully charged using WooCommerce Payments (Transaction #%s)', 'woocommerce-payments' ), wc_price( $amount ), $transaction_id ) );
			$order->payment_complete( $transaction_id );
		} else {
			$order->payment_complete();
		}

		wc_reduce_stock_levels( $order_id );
		WC()->cart->empty_cart();

		return array(
			'result' 	=> 'success',
			'redirect'	=> $this->get_return_url( $order ),
		);
	}

}
