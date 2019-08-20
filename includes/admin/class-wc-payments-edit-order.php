<?php
/**
 * Class WC_Payments_Edit_Order
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Controller for edit order screen actions.
 */
class WC_Payments_Edit_Order {

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * WC_Payments_Edit_Order constructor.
	 *
	 * @param WC_Payments_API_Client $payments_api_client - WooCommerce Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->payments_api_client = $payments_api_client;

		add_action( 'woocommerce_order_actions', array( $this, 'add_order_actions' ) );
		add_action( 'woocommerce_order_action_capture_charge', array( $this, 'capture_charge' ) );
		add_action( 'woocommerce_order_action_void_authorization', array( $this, 'void_authorization' ) );

		add_action( 'init', array( $this, 'register_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add capture and void actions for orders with an authorized charge.
	 *
	 * @param array $actions - Actions to make available in order actions metabox.
	 */
	public function add_order_actions( $actions ) {
		global $theorder;

		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID !== $theorder->get_payment_method() ) {
			return $actions;
		}

		if ( 'requires_capture' !== $theorder->get_meta( '_intention_status', true ) ) {
			return $actions;
		}

		$new_actions = array(
			'capture_charge'     => __( 'Capture charge', 'woocommerce-payments' ),
			'void_authorization' => __( 'Void authorization', 'woocommerce-payments' ),
		);

		return array_merge( $new_actions, $actions );
	}

	/**
	 * Capture previously authorized charge.
	 *
	 * @param WC_Order $order - Order to capture charge on.
	 */
	public function capture_charge( $order ) {
		$amount = $order->get_total();
		$intent = $this->payments_api_client->capture_intention( $order->get_transaction_id(), round( (float) $amount * 100 ) );
		$status = $intent->get_status();

		$order->update_meta_data( '_intention_status', $status );
		$order->save();

		if ( 'succeeded' === $status ) {
			$note = sprintf(
				/* translators: %1: the successfully charged amount */
				__( 'A payment of %1$s was <strong>successfully captured</strong> using WooCommerce Payments.', 'woocommerce-payments' ),
				wc_price( $amount )
			);
			$order->add_order_note( $note );
			$order->payment_complete();
		} else {
			$note = sprintf(
				/* translators: %1: the successfully charged amount */
				__( 'A capture of %1$s <strong>failed</strong> to complete.', 'woocommerce-payments' ),
				wc_price( $amount )
			);
			$order->add_order_note( $note );
		}
	}

	/**
	 * Void previously authorized charge.
	 *
	 * @param WC_Order $order - Order to void authorization on.
	 */
	public function void_authorization( $order ) {
		$intent = $this->payments_api_client->cancel_intention( $order->get_transaction_id() );
		$status = $intent->get_status();

		$order->update_meta_data( '_intention_status', $status );
		$order->save();

		if ( 'canceled' === $status ) {
			$order->update_status( 'cancelled', __( 'Payment authorization was successfully <strong>voided</strong>.', 'woocommerce-payments' ) );
		} else {
			$note = sprintf(
				/* translators: %1: the successfully charged amount */
				__( 'Voiding authorization <strong>failed</strong> to complete.', 'woocommerce-payments' ),
				wc_price( $amount )
			);
			$order->add_order_note( $note );
		}
	}

	/**
	 * Register the CSS and JS scripts
	 */
	public function register_scripts() {
		$script_src_url      = plugins_url( 'dist/edit-order.js', WCPAY_PLUGIN_FILE );
		$script_deps_path    = WCPAY_ABSPATH . 'dist/edit-order.deps.json';
		$script_dependencies = file_exists( $script_deps_path )
			? json_decode( file_get_contents( $script_deps_path ) ) // PHPCS:Ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			: array();
		wp_register_script(
			'WCPAY_EDIT_ORDER',
			$script_src_url,
			$script_dependencies,
			filemtime( WCPAY_ABSPATH . 'dist/edit-order.js' ),
			true
		);

		wp_register_style(
			'WCPAY_EDIT_ORDER',
			plugins_url( 'dist/edit-order.css', WCPAY_PLUGIN_FILE ),
			array( 'wc-components' ),
			filemtime( WCPAY_ABSPATH . 'dist/edit-order.css' )
		);
	}

	/**
	 * Load the assets
	 */
	public function enqueue_scripts() {
		if ( 'shop_order' !== get_current_screen()->id ) {
			return;
		}

		wp_enqueue_script( 'WCPAY_EDIT_ORDER' );
		wp_enqueue_style( 'WCPAY_EDIT_ORDER' );
	}
}
