<?php
/**
 * Experimental shopper-approved agent purchases.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service\AgentPurchases;

use RuntimeException;
use Throwable;
use WC_Payments;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/** Own the experimental agent API, shopper approval, and V1 payment flow. */
class Feature {
	/**
	 * Quote calculator.
	 *
	 * @var QuoteService
	 */
	private $quotes;

	/**
	 * Initialize the feature.
	 *
	 * @param QuoteService $quotes Quote calculator.
	 */
	public function __construct( QuoteService $quotes ) {
		$this->quotes = $quotes;
	}

	/** Determine whether the explicitly enabled test experiment can run. @return bool */
	public static function is_enabled(): bool {
		return 'yes' === get_option( 'wcpay_agent_purchases_experiment_enabled', 'no' )
			&& 'development' === wp_get_environment_type()
			&& WC_Payments::mode()->is_test()
			&& WC_Payments::mode()->is_dev();
	}

	/** Register the experimental purchase surfaces only when opted in. */
	public function register(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		add_action( 'rest_api_init', [ $this, 'routes' ] );
		add_action( 'template_redirect', [ $this, 'approval' ] );
		add_action( 'admin_menu', [ $this, 'register_settings' ] );
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'filter_website_gateways' ] );
	}

	/** Register the merchant controls. */
	public function register_settings(): void {
		if ( self::is_enabled() ) {
			add_submenu_page( 'woocommerce', __( 'Agent purchases (experimental)', 'woocommerce-payments' ), __( 'Agent purchases', 'woocommerce-payments' ), 'manage_woocommerce', 'wcpay-agent-purchases', [ $this, 'settings' ] );
		}
	}

	/** Filter website visibility independently of the agent channel.
	 *
	 * @param array $gateways Available gateways.
	 * @return array
	 */
	public function filter_website_gateways( array $gateways ): array {
		if ( self::is_enabled() && 'no' === get_option( 'wcpay_agent_purchases_web_enabled', 'yes' ) ) {
			unset( $gateways['woocommerce_payments'] );
		}
		return $gateways;
	}

	/** Enforce the experiment and test-account boundary.
	 *
	 * @throws RuntimeException If test purchases are unavailable.
	 */
	public function guard(): void {
		if ( ! self::is_enabled() || ! WC_Payments::get_account_service()->is_stripe_connected() ) {
			throw new RuntimeException( __( 'Agent purchases require the enabled experiment and a connected development test account.', 'woocommerce-payments' ) );
		}
		/**
		 * Validate additional requirements of the experimental payment provider.
		 *
		 * @since 11.2.0
		 */
		do_action( 'wcpay_agent_purchase_validate_environment' );
	}
	/**
	 * Authenticate a scoped agent key.
	 *
	 * @param WP_REST_Request $request Agent request.
	 * @return bool|WP_Error
	 */
	public function auth( WP_REST_Request $request ) {
		try {
			$this->guard();
		} catch ( Throwable $e ) {
			return new WP_Error( 'agent_purchase_unavailable', $e->getMessage(), [ 'status' => 403 ] );
		}
		$hash = get_option( 'wcpay_agent_purchase_key_hash', '' );
		return $hash && hash_equals( $hash, hash( 'sha256', (string) $request->get_header( 'X-WCPay-Agent' ) ) ) ? true : new WP_Error( 'agent_purchase_forbidden', __( 'A scoped agent key is required.', 'woocommerce-payments' ), [ 'status' => 403 ] );
	}
	/**
	 * Register the experimental REST endpoints.
	 */
	public function routes(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		foreach ( [
			'/products'                             => 'GET',
			'/quotes'                               => 'POST',
			'/quotes/(?P<id>[a-f0-9]{32})'          => 'GET',
			'/quotes/(?P<id>[a-f0-9]{32})/complete' => 'POST',
		] as $route => $method ) {
			register_rest_route(
				'wcpay/agent-purchases/v1',
				$route,
				[
					'methods'             => $method,
					'permission_callback' => [ $this, 'auth' ],
					'callback'            => [ $this, 'request' ],
					'args'                => '/quotes' === $route ? [
						'product_id'       => [
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						],
						'quantity'         => [
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 5,
							'default' => 1,
						],
						'shipping_address' => [
							'type'     => 'object',
							'required' => true,
						],
					] : [],
				]
			);
		}
	}
	/**
	 * Dispatch an authenticated agent request.
	 *
	 * @throws RuntimeException If the request cannot be completed (returned as a REST error).
	 *
	 * @param WP_REST_Request $request Agent request.
	 * @return array|WP_REST_Response|WP_Error
	 */
	public function request( WP_REST_Request $request ) {
		try {
			$this->guard();
			$route = $request->get_route();
			if ( '/products' === substr( $route, -9 ) ) {
				$ids      = array_filter( array_map( 'absint', (array) get_option( 'wcpay_agent_purchase_product_ids', [] ) ) );
				$products = $ids ? wc_get_products(
					[
						'limit'   => 20,
						'status'  => 'publish',
						'include' => $ids,
					]
				) : [];
				return [
					'products' => array_values(
						array_map(
							static fn( $p ) => [
								'id'       => $p->get_id(),
								'name'     => $p->get_name(),
								'price'    => $p->get_price(),
								'currency' => get_woocommerce_currency(),
								'in_stock' => $p->is_in_stock(),
							],
							$products
						)
					),
				];
			}
			if ( 'GET' === $request->get_method() ) {
				return $this->public_record( $this->record( $request['id'] ) );
			}
			if ( 'yes' !== get_option( 'wcpay_agent_purchases_enabled', 'no' ) ) {
				throw new RuntimeException( __( 'The merchant has disabled agent purchases.', 'woocommerce-payments' ) );
			}
			if ( '/complete' === substr( $route, -9 ) ) {
				return $this->complete( $request['id'] );
			}
			$address = $request->get_param( 'shipping_address' );
			if ( ! is_array( $address ) ) {
				throw new RuntimeException( __( 'A shipping address is required.', 'woocommerce-payments' ) );
			}
			$q = $this->quotes->calculate( (int) $request['product_id'], (int) ( $request['quantity'] ?? 1 ), $address );
			if ( 'USD' !== $q['currency'] || (float) $q['total'] <= 0 || (float) $q['total'] > 100 ) {
				throw new RuntimeException( __( 'Experimental purchases must be between $0 and $100 USD.', 'woocommerce-payments' ) );
			}
			$record = [
				'id'         => bin2hex( random_bytes( 16 ) ),
				'owner'      => (int) get_option( 'wcpay_agent_purchase_shopper_id' ),
				'state'      => 'awaiting_approval',
				'quote'      => $q,
				'expires_at' => time() + 600,
				'order_id'   => null,
			];
			if ( ! $record['owner'] ) {
				throw new RuntimeException( __( 'Set up the experiment shopper first.', 'woocommerce-payments' ) );
			}
			add_option( $this->key( $record['id'] ), $record, '', false );
			return new WP_REST_Response( $this->public_record( $record ), 201 );
		} catch ( Throwable $e ) {
			return new WP_Error( 'agent_purchase_rejected', $e->getMessage(), [ 'status' => 409 ] );
		}
	}
	/**
	 * Render the shopper-owned approval page and process its consent form.
	 *
	 * @throws RuntimeException If consent cannot be accepted (rendered as an error).
	 */
	public function approval(): void {
		if ( ! self::is_enabled() || ! isset( $_GET['wcpay_agent_purchase'] ) ) {
			return;
		}
		nocache_headers();
		try {
			$this->guard();
			$id = sanitize_text_field( wp_unslash( $_GET['wcpay_agent_purchase'] ) );
			if ( ! preg_match( '/^[a-f0-9]{32}$/', $id ) ) {
				throw new RuntimeException( __( 'Invalid quote.', 'woocommerce-payments' ) );
			}
			$r = $this->record( $id );
			if ( ! is_user_logged_in() ) {
				wp_safe_redirect( wp_login_url( $this->public_record( $r )['approval_url'] ) );
				exit;
			}
			if ( get_current_user_id() !== $r['owner'] ) {
				wp_die( 'Sign in as the shopper who owns this purchase.', 'Shopper required', [ 'response' => 403 ] );
			}
			$lock = $this->key( $id ) . '_lock';
			if ( ! add_option( $lock, time(), '', false ) ) {
				throw new RuntimeException( __( 'Purchase is processing. Refresh shortly.', 'woocommerce-payments' ) );
			}
			$redirect_after_save = false;
			try {
				$r = $this->record( $id );
				if ( in_array( $r['state'], [ 'awaiting_approval', 'approved' ], true ) ) {
					try {
						$this->fresh( $r );
					} catch ( Throwable $e ) {
						$r['state'] = time() > $r['expires_at'] ? 'expired' : 'changed';
						$this->save( $r );
					}
				}
				if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
					if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wcpay_agent_purchase_approve_' . $id ) || sanitize_text_field( wp_unslash( $_POST['quote_id'] ?? '' ) ) !== $id ) {
						throw new RuntimeException( __( 'Approval could not be verified. Reload this page.', 'woocommerce-payments' ) );
					}
					if ( 'awaiting_approval' !== $r['state'] ) {
						throw new RuntimeException( __( 'This quote can no longer be approved.', 'woocommerce-payments' ) );
					}
					if ( 'yes' !== get_option( 'wcpay_agent_purchases_enabled', 'no' ) ) {
						throw new RuntimeException( __( 'The merchant has disabled agent purchases.', 'woocommerce-payments' ) );
					}
					$decision = sanitize_text_field( wp_unslash( $_POST['decision'] ?? '' ) );
					if ( ! in_array( $decision, [ 'approve', 'cancel' ], true ) ) {
						throw new RuntimeException( __( 'Choose approve or cancel.', 'woocommerce-payments' ) );
					}
					$r['state'] = 'approve' === $decision ? 'approved' : 'cancelled';
					if ( 'approved' === $r['state'] ) {
						$r['approved_fingerprint'] = $r['quote']['fingerprint'];
					}
					$this->save( $r );
					$redirect_after_save = true;
				}
			} finally {
				delete_option( $lock );
			}
			if ( $redirect_after_save ) {
				wp_safe_redirect( $this->public_record( $r )['approval_url'] );
				exit;
			}
			$view = [
				'state'       => $r['state'],
				'quote'       => $r['quote'],
				'address'     => $r['quote']['shipping_address'],
				'quote_id'    => $id,
				'nonce'       => wp_create_nonce( 'wcpay_agent_purchase_approve_' . $id ),
				'order_id'    => $r['order_id'],
				'form_action' => $this->public_record( $r )['approval_url'],
			];
			if ( in_array( $r['state'], [ 'processing', 'needs_attention' ], true ) ) {
				$view['state']   = 'error';
				$view['message'] = 'This test payment needs review. Do not start another payment for this order.';
			}
		} catch ( Throwable $e ) {
			$view = [
				'state'   => 'error',
				'message' => $e->getMessage(),
			];
		}
		$view['css_url'] = plugins_url( 'assets/css/agent-purchases.css', WCPAY_PLUGIN_FILE );
		require WCPAY_ABSPATH . 'templates/agent-purchases/approval.php';
		exit;
	}
	/**
	 * Render and save independent merchant channel controls.
	 */
	public function settings(): void {
		if ( ! self::is_enabled() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( isset( $_POST['agent_purchase_settings'] ) ) {
			check_admin_referer( 'wcpay_agent_purchase_settings' );
			update_option( 'wcpay_agent_purchases_enabled', isset( $_POST['agent_enabled'] ) ? 'yes' : 'no' );
			update_option( 'wcpay_agent_purchases_web_enabled', isset( $_POST['web_enabled'] ) ? 'yes' : 'no' );
		}
		echo '<div class="wrap"><h1>Agent purchases (experimental)</h1><p>Local test payments only. Website checkout can use WooPayments or another enabled gateway.</p><form method="post">';
		wp_nonce_field( 'wcpay_agent_purchase_settings' );
		foreach ( [
			'agent' => 'Enable WooPayments for agent purchases',
			'web'   => 'Show WooPayments in website checkout',
		] as $key => $label ) {
			$option = 'agent' === $key ? 'wcpay_agent_purchases_enabled' : 'wcpay_agent_purchases_web_enabled';
			echo '<p><label><input type="checkbox" name="' . esc_attr( $key ) . '_enabled" ' . checked( 'yes', get_option( $option, 'agent' === $key ? 'no' : 'yes' ), false ) . '> ' . esc_html( $label ) . '</label></p>';
		}
		echo '<input type="hidden" name="agent_purchase_settings" value="1">';
		submit_button();
		echo '</form></div>';
	}
	/**
	 * Build a quote storage key.
	 *
	 * @param string $id Quote identifier.
	 * @return string
	 */
	private function key( string $id ): string {
		return 'wcpay_agent_purchase_quote_' . $id;
	}
	/**
	 * Read an existing quote.
	 *
	 * @param string $id Quote identifier.
	 * @return array
	 * @throws RuntimeException When the quote does not exist.
	 */
	private function record( string $id ): array {
		$r = get_option( $this->key( $id ) );
		if ( ! is_array( $r ) ) {
			throw new RuntimeException( __( 'Quote not found.', 'woocommerce-payments' ) );
		}
		return $r;
	}
	/**
	 * Persist a quote while its mutation lock is held.
	 *
	 * @param array $record Quote record.
	 */
	private function save( array $record ): void {
		update_option( $this->key( $record['id'] ), $record, false );
	}
	/**
	 * Return the agent-visible purchase state.
	 *
	 * @param array $record Quote record.
	 * @return array
	 */
	private function public_record( array $record ): array {
		unset( $record['owner'], $record['approved_fingerprint'] );
		$record['approval_url'] = add_query_arg( 'wcpay_agent_purchase', $record['id'], home_url( '/' ) );
		return $record;
	}
	/**
	 * Require an unexpired quote matching the current store totals.
	 *
	 * @param array $record Quote record.
	 * @throws RuntimeException When the approved purchase has changed.
	 */
	private function fresh( array $record ): void {
		if ( time() > $record['expires_at'] ) {
			throw new RuntimeException( __( 'Quote expired. Request a new quote and approval.', 'woocommerce-payments' ) );
		}
		$q     = $record['quote'];
		$fresh = $this->quotes->calculate( $q['product_id'], $q['quantity'], $q['shipping_address'] );
		if ( ! hash_equals( $q['fingerprint'], $fresh['fingerprint'] ) ) {
			throw new RuntimeException( __( 'Purchase changed. Request a new quote and approval.', 'woocommerce-payments' ) );
		}
	}
	/**
	 * Pay once after explicit approval of the current quote.
	 *
	 * @param string $id Quote identifier.
	 * @return array
	 * @throws RuntimeException When consent or payment authorization is unavailable.
	 */
	private function complete( string $id ): array {
		$lock = $this->key( $id ) . '_lock';
		if ( ! add_option( $lock, time(), '', false ) ) {
			throw new RuntimeException( __( 'This purchase is already being processed.', 'woocommerce-payments' ) );
		}
		$old_post         = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preserve the gateway input; REST authentication and stored shopper consent are verified separately.
		$filter           = null;
		$provider_started = false;
		try {
			$r     = $this->record( $id );
			$order = $r['order_id'] ? wc_get_order( $r['order_id'] ) : false;
			if ( $order && ( $order->get_customer_id() !== $r['owner'] || 'wcpay-agent-purchase' !== $order->get_created_via() || 'yes' !== $order->get_meta( '_wcpay_agent_purchase' ) ) ) {
				throw new RuntimeException( __( 'The stored order does not belong to this agent purchase.', 'woocommerce-payments' ) );
			}
			if ( $order && $order->is_paid() && $order->get_transaction_id() ) {
				$r['state'] = 'paid';
				$this->save( $r );
				return $this->public_record( $r );
			}
			if ( 'approved' !== $r['state'] || $order ) {
				throw new RuntimeException( __( 'Explicit shopper approval is required; unresolved payments cannot be automatically retried.', 'woocommerce-payments' ) );
			}
			$this->fresh( $r );
			if ( ! hash_equals( $r['quote']['fingerprint'], $r['approved_fingerprint'] ?? '' ) ) {
				throw new RuntimeException( __( 'Approval does not match this purchase.', 'woocommerce-payments' ) );
			}
			/**
			 * Supply a payment method authorized for this approved experimental quote.
			 *
			 * @since 11.2.0
			 * @param string $payment_method Authorized payment method, empty by default.
			 * @param array $record Approved quote record.
			 */
			$payment_method = apply_filters( 'wcpay_agent_purchase_payment_method', '', $r );
			if ( ! is_string( $payment_method ) || '' === $payment_method ) {
				throw new RuntimeException( __( 'No payment authorization provider is configured for this experiment.', 'woocommerce-payments' ) );
			}
			$order         = $this->quotes->make_order( $r['quote'], $r['owner'] );
			$r['order_id'] = $order->get_id();
			$r['state']    = 'processing';
			$this->save( $r );
			wc_reserve_stock_for_order( $order );
			if ( ! WC()->session || ! WC()->cart ) {
				wc_load_cart();
			}
			$filter = static function ( $params, $path, $method ) use ( $id ) {
				if ( 'POST' === strtoupper( $method ) && 'intentions' === trim( $path, '/' ) ) {
					$params['idempotency_key'] = 'agent-purchase-' . $id;
				}
				return $params;
			};
			add_filter( 'wcpay_api_request_params', $filter, 10, 3 );
			$provider_started = true;
			/**
			 * Prepare test-provider state immediately before the V1 gateway call.
			 *
			 * @since 11.2.0
			 */
			do_action( 'wcpay_agent_purchase_before_payment' );
			$_POST = [
				'payment_method'                        => 'woocommerce_payments',
				'wcpay-payment-method'                  => $payment_method,
				'wc-woocommerce_payments-payment-token' => 'new',
				'wcpay-fraud-prevention-token'          => \WCPay\Fraud_Prevention\Fraud_Prevention_Service::get_instance()->get_token(),
			];
			WC_Payments::get_gateway()->process_payment( $order->get_id() );
			$order      = wc_get_order( $order->get_id() );
			$r['state'] = $order->is_paid() && $order->get_transaction_id() ? 'paid' : 'needs_attention';
			$this->save( $r );
			return $this->public_record( $r );
		} finally {
			$_POST = $old_post;
			if ( $filter ) {
				remove_filter( 'wcpay_api_request_params', $filter, 10 );
			}
			try {
				if ( $provider_started ) {
					/**
					 * Restore provider state after an attempted experimental payment.
					 *
					 * @since 11.2.0
					 */
					do_action( 'wcpay_agent_purchase_after_payment' );
				}
			} finally {
				delete_option( $lock );
			}
		}
	}
}
