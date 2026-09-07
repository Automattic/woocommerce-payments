<?php
/**
 * Plugin Name: WooPayments agent purchase demo
 * Description: Development-only, explicitly approved test purchases through WooPayments V1.
 * Version: 0.1.0
 */
defined( 'ABSPATH' ) || exit;

final class WCPay_Agent_Demo {
	public static function boot(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'template_redirect', array( self::class, 'approval' ) );
		add_action( 'admin_menu', static function () { add_submenu_page( 'woocommerce', 'Agent purchase demo', 'Agent purchase demo', 'manage_woocommerce', 'wcpay-agent-demo', array( self::class, 'settings' ) ); } );
		add_filter( 'woocommerce_available_payment_gateways', static function ( $gateways ) {
			if ( 'development' === wp_get_environment_type() && 'no' === get_option( 'wcpay_agent_demo_web_enabled', 'yes' ) ) { unset( $gateways['woocommerce_payments'] ); }
			return $gateways;
		} );
	}
	public static function guard(): void {
		$redirect = wp_parse_url( (string) get_option( 'wcpaydev_redirect_to' ) );
		if ( 'development' !== wp_get_environment_type() || ! class_exists( 'WC_Payments' ) || ! WC_Payments::mode()->is_test() || ! WC_Payments::mode()->is_dev() || ! get_option( 'wcpaydev_redirect' ) || 'host.docker.internal' !== ( $redirect['host'] ?? '' ) || 8086 !== (int) ( $redirect['port'] ?? 0 ) || ! WC_Payments::get_account_service()->is_stripe_connected() ) {
			throw new RuntimeException( 'This demo requires the connected local V1 development store in test mode.' );
		}
		require_once __DIR__ . '/class-demo-quote.php';
	}
	public static function auth( WP_REST_Request $request ) {
		try { self::guard(); } catch ( Throwable $e ) { return new WP_Error( 'demo_unavailable', $e->getMessage(), array( 'status' => 403 ) ); }
		$hash = get_option( 'wcpay_agent_demo_key_hash', '' );
		return $hash && hash_equals( $hash, hash( 'sha256', (string) $request->get_header( 'X-WCPay-Agent-Demo' ) ) ) ? true : new WP_Error( 'demo_forbidden', 'A scoped demo agent key is required.', array( 'status' => 403 ) );
	}
	public static function routes(): void {
		foreach ( array( '/products' => 'GET', '/quotes' => 'POST', '/quotes/(?P<id>[a-f0-9]{32})' => 'GET', '/quotes/(?P<id>[a-f0-9]{32})/complete' => 'POST' ) as $route => $method ) {
			register_rest_route( 'wcpay-agent-demo/v1', $route, array( 'methods' => $method, 'permission_callback' => array( self::class, 'auth' ), 'callback' => array( self::class, 'request' ) ) );
		}
	}
	private static function key( string $id ): string { return 'wcpay_agent_demo_quote_' . $id; }
	private static function record( string $id ): array {
		$r = get_option( self::key( $id ) );
		if ( ! is_array( $r ) ) { throw new RuntimeException( 'Quote not found.' ); }
		return $r;
	}
	private static function save( array $record ): void { update_option( self::key( $record['id'] ), $record, false ); }
	private static function public_record( array $record ): array {
		unset( $record['owner'], $record['approved_fingerprint'] );
		$record['approval_url'] = rtrim( get_option( 'wcpay_agent_demo_origin', 'http://localhost:8082' ), '/' ) . '/?wcpay_agent_demo=' . $record['id'];
		return $record;
	}
	private static function fresh( array $record ): void {
		if ( time() > $record['expires_at'] ) { throw new RuntimeException( 'Quote expired. Request a new quote and approval.' ); }
		$q = $record['quote'];
		$fresh = WCPay_Agent_Demo_Quote::calculate( $q['product_id'], $q['quantity'], $q['shipping_address'] );
		if ( ! hash_equals( $q['fingerprint'], $fresh['fingerprint'] ) ) { throw new RuntimeException( 'Purchase changed. Request a new quote and approval.' ); }
	}
	public static function request( WP_REST_Request $request ) {
		try {
			$route = $request->get_route();
			if ( str_ends_with( $route, '/products' ) ) {
				$products = wc_get_products( array( 'limit' => 20, 'status' => 'publish', 'include' => array( (int) get_option( 'wcpay_agent_demo_product_id' ) ) ) );
				return array( 'products' => array_values( array_map( static fn( $p ) => array( 'id' => $p->get_id(), 'name' => $p->get_name(), 'price' => $p->get_price(), 'currency' => get_woocommerce_currency(), 'in_stock' => $p->is_in_stock() ), array_filter( $products, static fn( $p ) => 'yes' === $p->get_meta( '_wcpay_agent_demo_product' ) ) ) ) );
			}
			if ( 'GET' === $request->get_method() ) { return self::public_record( self::record( $request['id'] ) ); }
			if ( 'yes' !== get_option( 'wcpay_agent_demo_enabled', 'no' ) ) { throw new RuntimeException( 'The merchant has disabled agent purchases.' ); }
			if ( str_ends_with( $route, '/complete' ) ) { return self::complete( $request['id'] ); }
			$address = array( 'first_name' => 'Demo', 'last_name' => 'Shopper', 'address_1' => '123 Demo Street', 'address_2' => '', 'city' => 'San Francisco', 'state' => 'CA', 'postcode' => '94107', 'country' => 'US' );
			$q = WCPay_Agent_Demo_Quote::calculate( (int) $request['product_id'], (int) ( $request['quantity'] ?? 1 ), $address );
			if ( 'USD' !== $q['currency'] || (float) $q['total'] <= 0 || (float) $q['total'] > 100 ) { throw new RuntimeException( 'Demo purchases must be between $0 and $100 USD.' ); }
			$record = array( 'id' => bin2hex( random_bytes( 16 ) ), 'owner' => (int) get_option( 'wcpay_agent_demo_shopper_id' ), 'state' => 'awaiting_approval', 'quote' => $q, 'expires_at' => time() + 600, 'order_id' => null );
			if ( ! $record['owner'] ) { throw new RuntimeException( 'Set up the demo shopper first.' ); }
			add_option( self::key( $record['id'] ), $record, '', false );
			return new WP_REST_Response( self::public_record( $record ), 201 );
		} catch ( Throwable $e ) { return new WP_Error( 'demo_rejected', $e->getMessage(), array( 'status' => 409 ) ); }
	}
	private static function complete( string $id ): array {
		$lock = self::key( $id ) . '_lock';
		if ( ! add_option( $lock, time(), '', false ) ) { throw new RuntimeException( 'This purchase is already being processed.' ); }
		$old_post = $_POST;
		$filter = null;
		try {
			$r = self::record( $id );
			$order = $r['order_id'] ? wc_get_order( $r['order_id'] ) : false;
			if ( $order && $order->is_paid() && $order->get_transaction_id() ) { $r['state'] = 'paid'; self::save( $r ); return self::public_record( $r ); }
			if ( 'approved' !== $r['state'] || $order ) { throw new RuntimeException( 'Explicit shopper approval is required; unresolved payments cannot be automatically retried.' ); }
			self::fresh( $r );
			if ( ! hash_equals( $r['quote']['fingerprint'], $r['approved_fingerprint'] ?? '' ) ) { throw new RuntimeException( 'Approval does not match this purchase.' ); }
			$order = WCPay_Agent_Demo_Quote::make_order( $r['quote'], $r['owner'] );
			$r['order_id'] = $order->get_id();
			$r['state'] = 'processing';
			self::save( $r );
			wc_reserve_stock_for_order( $order );
			if ( ! WC()->session || ! WC()->cart ) { wc_load_cart(); }
			$filter = static function ( $params, $path, $method ) use ( $id ) {
				if ( 'POST' === strtoupper( $method ) && 'intentions' === trim( $path, '/' ) ) { $params['idempotency_key'] = 'agent-demo-' . $id; }
				return $params;
			};
			add_filter( 'wcpay_api_request_params', $filter, 10, 3 );
			add_filter( 'pre_wp_mail', '__return_true' );
			$_POST = array( 'payment_method' => 'woocommerce_payments', 'wcpay-payment-method' => 'pm_card_visa', 'wc-woocommerce_payments-payment-token' => 'new', 'wcpay-fraud-prevention-token' => \WCPay\Fraud_Prevention\Fraud_Prevention_Service::get_instance()->get_token() );
			WC_Payments::get_gateway()->process_payment( $order->get_id() );
			$order = wc_get_order( $order->get_id() );
			$r['state'] = $order->is_paid() && $order->get_transaction_id() ? 'paid' : 'needs_attention';
			self::save( $r );
			return self::public_record( $r );
		} finally {
			$_POST = $old_post;
			if ( $filter ) { remove_filter( 'wcpay_api_request_params', $filter, 10 ); }
			remove_filter( 'pre_wp_mail', '__return_true' );
			delete_option( $lock );
		}
	}
	public static function approval(): void {
		if ( ! isset( $_GET['wcpay_agent_demo'] ) ) { return; }
		nocache_headers();
		try {
			self::guard();
			$id = sanitize_text_field( wp_unslash( $_GET['wcpay_agent_demo'] ) );
			if ( ! preg_match( '/^[a-f0-9]{32}$/', $id ) ) { throw new RuntimeException( 'Invalid quote.' ); }
			$r = self::record( $id );
			if ( ! is_user_logged_in() ) {
				$origin = get_option( 'wcpay_agent_demo_origin', 'http://localhost:8082' );
				wp_redirect( $origin . '/wp-login.php?redirect_to=' . rawurlencode( self::public_record( $r )['approval_url'] ) ); exit;
			}
			if ( get_current_user_id() !== $r['owner'] ) { wp_die( 'Sign in as the shopper who owns this purchase.', 'Shopper required', array( 'response' => 403 ) ); }
			$lock = self::key( $id ) . '_lock';
			if ( ! add_option( $lock, time(), '', false ) ) { throw new RuntimeException( 'Purchase is processing. Refresh shortly.' ); }
			$redirect_after_save = false;
			try {
			$r = self::record( $id );
			if ( in_array( $r['state'], array( 'awaiting_approval', 'approved' ), true ) ) {
				try { self::fresh( $r ); } catch ( Throwable $e ) { $r['state'] = time() > $r['expires_at'] ? 'expired' : 'changed'; self::save( $r ); }
			}
			if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
				if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wcpay_agent_demo_approve_' . $id ) || $id !== ( $_POST['quote_id'] ?? '' ) ) { throw new RuntimeException( 'Approval could not be verified. Reload this page.' ); }
				if ( 'awaiting_approval' !== $r['state'] ) { throw new RuntimeException( 'This quote can no longer be approved.' ); }
				if ( 'yes' !== get_option( 'wcpay_agent_demo_enabled', 'no' ) ) { throw new RuntimeException( 'The merchant has disabled agent purchases.' ); }
				$decision = $_POST['decision'] ?? '';
				if ( ! in_array( $decision, array( 'approve', 'cancel' ), true ) ) { throw new RuntimeException( 'Choose approve or cancel.' ); }
				$r['state'] = 'approve' === $decision ? 'approved' : 'cancelled';
				if ( 'approved' === $r['state'] ) { $r['approved_fingerprint'] = $r['quote']['fingerprint']; }
				self::save( $r );
				$redirect_after_save = true;
			}
			} finally { delete_option( $lock ); }
			if ( $redirect_after_save ) { wp_safe_redirect( self::public_record( $r )['approval_url'] ); exit; }
			$view = array( 'state' => $r['state'], 'quote' => $r['quote'], 'address' => $r['quote']['shipping_address'], 'quote_id' => $id, 'nonce' => wp_create_nonce( 'wcpay_agent_demo_approve_' . $id ), 'order_id' => $r['order_id'], 'form_action' => '/?wcpay_agent_demo=' . $id );
			if ( in_array( $r['state'], array( 'processing', 'needs_attention' ), true ) ) { $view['state'] = 'error'; $view['message'] = 'This test payment needs review. Do not start another payment for this order.'; }
		} catch ( Throwable $e ) { $view = array( 'state' => 'error', 'message' => $e->getMessage() ); }
		$view['css_url'] = get_option( 'wcpay_agent_demo_origin', 'http://localhost:8082' ) . '/wp-content/plugins/woopayments-agent-demo/demo.css';
		require __DIR__ . '/approval.php'; exit;
	}
	public static function settings(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
		if ( isset( $_POST['demo_settings'] ) ) {
			check_admin_referer( 'wcpay_agent_demo_settings' );
			update_option( 'wcpay_agent_demo_enabled', isset( $_POST['agent_enabled'] ) ? 'yes' : 'no' );
			update_option( 'wcpay_agent_demo_web_enabled', isset( $_POST['web_enabled'] ) ? 'yes' : 'no' );
		}
		echo '<div class="wrap"><h1>Agent purchase demo</h1><p>Local test payments only. Website checkout can use WooPayments or another enabled gateway.</p><form method="post">';
		wp_nonce_field( 'wcpay_agent_demo_settings' );
		foreach ( array( 'agent' => 'Enable WooPayments for agent purchases', 'web' => 'Show WooPayments in website checkout' ) as $key => $label ) {
			$option = 'agent' === $key ? 'wcpay_agent_demo_enabled' : 'wcpay_agent_demo_web_enabled';
			echo '<p><label><input type="checkbox" name="' . esc_attr( $key ) . '_enabled" ' . checked( 'yes', get_option( $option, 'yes' ), false ) . '> ' . esc_html( $label ) . '</label></p>';
		}
		echo '<input type="hidden" name="demo_settings" value="1">'; submit_button(); echo '</form></div>';
	}
}
WCPay_Agent_Demo::boot();
