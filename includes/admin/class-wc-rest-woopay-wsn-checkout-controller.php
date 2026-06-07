<?php
/**
 * Class WC_REST_WooPay_WSN_Checkout_Controller
 *
 * Dev-mode-only bridge that lets the Woo Shopping Network SPA hand
 * off a multi-item cart to this merchant's WooPay checkout flow in
 * one call:
 *
 *   1. Empty the current WC cart on this merchant.
 *   2. For each `{ slug, quantity }` pair the SPA sent, add the
 *      matching WC_Product to the cart server-side. Slug lookup
 *      mirrors what `/wp-json/wc/store/v1/cart/add-item` accepts
 *      from public clients, but skips the cross-origin nonce dance
 *      that the public Store API needs (the SPA can't get a valid
 *      `Cart-Token` + nonce against a localhost merchant from
 *      `localhost:8090`).
 *   3. Build the same init-session body the existing
 *      `ajax_init_woopay` AJAX path uses, POST to WooPay's /init,
 *      and return the response so the SPA can navigate the browser
 *      to `redirect_url`.
 *
 * Cross-origin: the SPA lives on `localhost:8090`; the merchant on
 * `localhost:8082`. Permissive CORS is sent on this route because
 * the endpoint is already gated to dev mode (no permission leak
 * possible against a production merchant — the controller's check
 * 403s in test / live).
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

use WCPay\WooPay\WooPay_Session;

/**
 * REST controller — dev-mode WSN→WooPay one-shot checkout handoff.
 */
class WC_REST_WooPay_WSN_Checkout_Controller extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wcpay/v1';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'woopay/wsn-checkout';

	/**
	 * Maximum number of items the SPA can hand off in one call.
	 * The WSN cart caps itself well below this, but a sanity bound
	 * here prevents a malformed body from triggering hundreds of
	 * product lookups per request.
	 */
	const MAX_ITEMS = 50;

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handle_checkout' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'items' => [
							'type'     => 'array',
							'required' => true,
							'items'    => [
								'type'       => 'object',
								'properties' => [
									'slug'         => [
										'type'     => 'string',
										'required' => false,
										'sanitize_callback' => 'sanitize_title',
									],
									'product_id'   => [
										'type'     => 'integer',
										'required' => false,
										'sanitize_callback' => 'absint',
									],
									'quantity'     => [
										'type'     => 'integer',
										'required' => false,
										'default'  => 1,
										'minimum'  => 1,
										'maximum'  => 999,
										'sanitize_callback' => 'absint',
									],
									'variation_id' => [
										'type'     => 'integer',
										'required' => false,
										'sanitize_callback' => 'absint',
									],
								],
							],
						],
					],
				],
				[
					// Preflight passthrough so browsers happy with the
					// permissive CORS on the POST path send the actual
					// request without the gate rejecting OPTIONS.
					'methods'             => 'OPTIONS',
					'callback'            => '__return_true',
					'permission_callback' => '__return_true',
				],
			]
		);

		// Send CORS headers on every response from this route. Filter
		// fires AFTER the route is matched so we don't bleed the
		// permissive header onto unrelated endpoints.
		add_filter( 'rest_post_dispatch', [ $this, 'add_cors_headers' ], 10, 3 );
	}

	/**
	 * Permission check — endpoint only available in dev mode (same
	 * gate the marketplace-bridge controller uses). The handoff
	 * empties the merchant's cart, so we don't want a production
	 * customer's cart wiped by a speculative POST.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return WC_Payments::mode()->is_dev();
	}

	/**
	 * Permissive CORS headers for the WSN handoff route. The SPA at
	 * `localhost:8090` POSTs across-origin to `localhost:8082`; the
	 * default REST CORS policy on a WordPress site doesn't allow
	 * cross-origin POSTs with `Content-Type: application/json`. The
	 * dev-mode gate above keeps this from leaking on production
	 * merchants.
	 *
	 * @param WP_HTTP_Response $response Outgoing response.
	 * @param WP_REST_Server   $server   Server instance.
	 * @param WP_REST_Request  $request  Original request.
	 * @return WP_HTTP_Response
	 */
	public function add_cors_headers( $response, $server, $request ) {
		$route = $request->get_route();
		if ( '/' . $this->namespace . '/' . $this->rest_base !== $route ) {
			return $response;
		}
		$origin = $request->get_header( 'origin' );
		if ( null === $origin ) {
			$origin = '*';
		}
		$response->header( 'Access-Control-Allow-Origin', $origin );
		$response->header( 'Access-Control-Allow-Methods', 'POST, OPTIONS' );
		$response->header( 'Access-Control-Allow-Headers', 'Content-Type' );
		return $response;
	}

	/**
	 * Handle the checkout handoff. Populates the WC cart from the
	 * SPA-supplied items, builds the WooPay init session body, POSTs
	 * to WooPay /init, and returns the response (with `redirect_url`)
	 * straight through.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_checkout( WP_REST_Request $request ) {
		$items_raw = (array) $request->get_param( 'items' );
		if ( empty( $items_raw ) ) {
			return new WP_Error(
				'wsn_checkout_no_items',
				__( 'No items supplied.', 'woocommerce-payments' ),
				[ 'status' => 400 ]
			);
		}
		if ( count( $items_raw ) > self::MAX_ITEMS ) {
			$items_raw = array_slice( $items_raw, 0, self::MAX_ITEMS );
		}

		// WC's REST API runtime doesn't auto-instantiate the cart or
		// customer the way the front-end bootstrap does, so a fresh
		// request lands here with `WC()->cart === null`. The Store
		// API gets around this by calling `wc_load_cart()`; mirror
		// that here so subsequent `WC()->cart->*` calls work.
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( function_exists( 'WC' ) && WC() && WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		if ( ! WC()->cart ) {
			return new WP_Error(
				'wsn_checkout_cart_unavailable',
				__( 'WooCommerce cart is not available on this request.', 'woocommerce-payments' ),
				[ 'status' => 500 ]
			);
		}

		WC()->cart->empty_cart();

		// Suppress the per-item calculate_totals() that fires via the
		// woocommerce_add_to_cart hook so we do one recalculation at
		// the end instead of one per item (N+1 → 1 for the loop).
		remove_action( 'woocommerce_add_to_cart', [ WC()->cart, 'calculate_totals' ], 20 );

		$added = 0;
		foreach ( $items_raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$resolved = $this->resolve_item( $item );
			if ( null === $resolved ) {
				continue;
			}
			$result = WC()->cart->add_to_cart(
				$resolved['product']->get_id(),
				$resolved['quantity'],
				$resolved['variation_id'],
				$resolved['variation']
			);
			if ( $result ) {
				++$added;
			}
		}

		add_action( 'woocommerce_add_to_cart', [ WC()->cart, 'calculate_totals' ], 20 );

		if ( 0 === $added ) {
			return new WP_Error(
				'wsn_checkout_add_failed',
				__( 'Could not add any of the supplied items to the cart.', 'woocommerce-payments' ),
				[ 'status' => 422 ]
			);
		}

		WC()->cart->calculate_totals();

		// Signal wsn-express attribution to WSN_Order_Attribution via the WC
		// session. The session persists through the WooPay handoff — when
		// WooPay's server completes the checkout via Store-API, the attribution
		// hook reads this flag and stamps wsn-express on the real order instead
		// of copying the wsn-pdp UTM the shopper's browser carried (a shopper
		// who clicked a WSN PDP link before paying via WooPay express would
		// otherwise be attributed to wsn-pdp, which is wrong).
		if ( WC()->session && class_exists( 'WSN_Order_Attribution' ) ) {
			WC()->session->set( WSN_Order_Attribution::SESSION_EXPRESS_FLAG, true );
		}

		// Build the unclaimed-session payload the WooPay express
		// button uses (`get_frontend_init_session_request()` packages
		// `get_init_session_request()` + AES-encrypts it with the
		// merchant's blog_token). The cart we just populated is read
		// inside `get_init_session_request()` via `/wc/store/v1/cart`,
		// so the WooPay session inherits the WSN cart server-side.
		$session_payload = WooPay_Session::get_frontend_init_session_request();
		if ( empty( $session_payload ) || ! isset( $session_payload['blog_id'] ) ) {
			return new WP_Error(
				'wsn_checkout_session_payload_failed',
				__( 'Could not build a WooPay session payload (missing blog token?).', 'woocommerce-payments' ),
				[ 'status' => 500 ]
			);
		}

		$body = [
			'blog_id'      => $session_payload['blog_id'],
			'data'         => $session_payload['data'],
			'is_test_mode' => WC_Payments::mode()->is_test(),
			'source_url'   => home_url(),
		];

		// The unclaimed `/sessions` endpoint doesn't require Jetpack
		// signing — the request is authenticated by the encrypted
		// `data` blob (HMAC'd with the merchant's blog_token). Use
		// `wp_remote_post` so we don't pull in the Jetpack signing
		// path the regular `init_woopay` flow uses; that path 401s
		// on dev merchants without a real Jetpack connection.
		$timeout = 30;
		$args    = [
			'method'  => 'POST',
			'timeout' => $timeout,
			'body'    => wp_json_encode( $body ),
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
		];

		// Hit the dev-only `/wsn-sessions` route — same body as
		// `/sessions` but the WooPay side skips the iframe-nonce
		// check because we can't reach the Connect iframe from the
		// WSN SPA. Falls back to `/sessions` so production sandboxes
		// (where the dev-only route isn't registered) still work
		// when WooPay-eligibility + nonce are properly set up.
		// The fallback timeout is capped at the remaining budget so
		// the two hops together never exceed the original 30s.
		$start        = microtime( true );
		$sessions_url = \WCPay\WooPay\WooPay_Utilities::get_woopay_rest_url( 'wsn-sessions' );
		$response     = wp_remote_post( $sessions_url, $args );
		if ( ! is_wp_error( $response ) && 404 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$sessions_url    = \WCPay\WooPay\WooPay_Utilities::get_woopay_rest_url( 'sessions' );
			$args['timeout'] = max( 5, $timeout - (int) ( microtime( true ) - $start ) );
			$response        = wp_remote_post( $sessions_url, $args );
		}
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'wsn_checkout_woopay_unreachable',
				$response->get_error_message(),
				[ 'status' => 502 ]
			);
		}

		$response_body = wp_remote_retrieve_body( $response );
		$decoded       = json_decode( $response_body, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'wsn_checkout_woopay_bad_response',
				__( 'WooPay returned an unexpected response.', 'woocommerce-payments' ),
				[ 'status' => 502 ]
			);
		}

		// Decorate the response with the count of lines we actually
		// landed on the cart so the SPA can surface a partial-success
		// message ("3 of 4 items added") when slug resolution misses.
		$decoded['wsn_items_added']     = $added;
		$decoded['wsn_items_requested'] = count( $items_raw );

		return rest_ensure_response( $decoded );
	}

	/**
	 * Resolve an item payload to a `WC_Product` plus the quantity and
	 * variation context needed for `WC()->cart->add_to_cart`. Returns
	 * null when no product can be resolved — caller skips the line
	 * silently so a partial cart still hands off cleanly.
	 *
	 * Variable products: the WSN side sends `variation_id` (surfaced by
	 * the dev-bridge projection); we hydrate the WC_Product_Variation
	 * here and read its WC-shape attribute map off the post directly so
	 * the WSN doesn't need to know whether each key is taxonomy
	 * (`attribute_pa_color`) or custom (`attribute_color`).
	 *
	 * @param array $item Single item payload from the SPA.
	 * @return array{product: WC_Product, quantity: int, variation_id: int, variation: array}|null
	 */
	private function resolve_item( array $item ): ?array {
		$quantity = isset( $item['quantity'] ) ? max( 1, (int) $item['quantity'] ) : 1;

		$product    = null;
		$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
		if ( $product_id > 0 ) {
			$candidate = wc_get_product( $product_id );
			if ( $candidate instanceof WC_Product ) {
				$product = $candidate;
			}
		}

		if ( ! $product ) {
			$slug = isset( $item['slug'] ) ? sanitize_title( (string) $item['slug'] ) : '';
			if ( '' !== $slug ) {
				$post = get_page_by_path( $slug, OBJECT, 'product' );
				if ( $post instanceof WP_Post ) {
					$candidate = wc_get_product( $post->ID );
					if ( $candidate instanceof WC_Product ) {
						$product = $candidate;
					}
				}
			}
		}

		if ( ! $product ) {
			return null;
		}

		// Variable-product context. The WSN side hands off just the
		// `variation_id`; we hydrate the WC_Product_Variation here and
		// pull its attribute map straight off the post, which already
		// has the merchant's exact taxonomy-vs-custom shape baked in.
		// That avoids the WSN needing to know whether each attribute
		// key is `attribute_pa_color` (taxonomy) or `attribute_color`
		// (custom) — info that depends on the merchant's WC config.
		$variation    = [];
		$variation_id = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
		if ( $variation_id > 0 ) {
			$variation_product = wc_get_product( $variation_id );
			if ( $variation_product instanceof WC_Product_Variation ) {
				$parent = wc_get_product( $variation_product->get_parent_id() );
				foreach ( (array) $variation_product->get_variation_attributes() as $attr_key => $attr_value ) {
					if ( '' !== (string) $attr_value ) {
						// Specific value — use as-is.
						$variation[ (string) $attr_key ] = sanitize_text_field( (string) $attr_value );
					} elseif ( $parent instanceof WC_Product ) {
						// "Any" slot: WC requires a concrete value for add_to_cart.
						// Pick the first valid term so the WSN express handoff lands
						// a purchasable cart line. The shopper can adjust on the WooPay
						// checkout page if a more specific selection matters.
						$attribute_name = preg_replace( '/^attribute_/', '', $attr_key );
						$parent_attr    = $parent->get_attribute( $attribute_name );
						if ( ! empty( $parent_attr ) ) {
							$slugs = array_map( 'sanitize_title', array_map( 'trim', explode( '|', $parent_attr ) ) );
						} else {
							$terms = wc_get_product_terms( $parent->get_id(), $attribute_name, [ 'fields' => 'slugs' ] );
							$slugs = is_array( $terms ) ? $terms : [];
						}
						if ( ! empty( $slugs ) ) {
							$variation[ (string) $attr_key ] = sanitize_text_field( reset( $slugs ) );
						}
					}
				}
			}
		}

		return [
			'product'      => $product,
			'quantity'     => $quantity,
			'variation_id' => $variation_id,
			'variation'    => $variation,
		];
	}
}
