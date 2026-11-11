<?php
/**
 * Class Utils
 *
 * @package WooCommerce\Payments\Utils
 */

namespace WCPay\MultiCurrency;

use Automattic\WooCommerce\Currency\ActiveCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi-Currency Utils.
 */
class Utils {

	/**
	 * Checks backtrace calls to see if a certain call has been made.
	 *
	 * @param array $calls Array of the calls to check for.
	 *
	 * @return bool True if found, false if not.
	 */
	public function is_call_in_backtrace( array $calls ): bool {
		$backtrace = wp_debug_backtrace_summary( null, 0, false ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		foreach ( $calls as $call ) {
			if ( in_array( $call, $backtrace, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns the request context WooCommerce classified the current request with: `store_api`, `rest`, `cron`,
	 * `ajax`, `admin` or `frontend`.
	 *
	 * @return string|null
	 */
	public static function get_request_context(): ?string {
		return WC()->get_request_context();
	}

	/**
	 * REST requests being dispatched, innermost last. Each entry holds the request object itself (compared by identity,
	 * as object ids are reused once an object is freed), whether the route serves a shopper and whether the user can
	 * manage WooCommerce, both known only once the route is matched and the user authenticated.
	 *
	 * @var array<int, array{request: object, shopper: bool, admin: bool, currency: ?string}>
	 */
	private static $rest_routes = [];

	/**
	 * Records that a REST route is about to run its callbacks. Nested dispatches (`rest_do_request()`) stack.
	 *
	 * @param \WP_REST_Request $request The request being dispatched.
	 * @param bool             $shopper  Whether the route serves a shopper (the Store API).
	 * @param bool             $admin    Whether the user can manage WooCommerce.
	 * @param string|null      $currency The request's own `currency` query parameter, upper-cased, if any.
	 *
	 * @return void
	 */
	public static function enter_rest_route( $request, bool $shopper, bool $admin, ?string $currency = null ) {
		self::leave_rest_route( $request );
		self::$rest_routes[] = [
			'request'  => $request,
			'shopper'  => $shopper,
			'admin'    => $admin,
			'currency' => $currency,
		];
	}

	/**
	 * Records that a REST route finished running its callbacks, dropping it and any route entered after it that never
	 * got to leave. Unknown requests are ignored.
	 *
	 * @param \WP_REST_Request $request The request that was dispatched.
	 *
	 * @return void
	 */
	public static function leave_rest_route( $request ) {
		foreach ( self::$rest_routes as $index => $route ) {
			if ( $route['request'] === $request ) {
				self::$rest_routes = array_slice( self::$rest_routes, 0, $index );
				return;
			}
		}
	}

	/**
	 * Forgets every REST route in progress. For tests and for resetting after a request that was abandoned.
	 *
	 * @return void
	 */
	public static function reset_rest_routes() {
		self::$rest_routes = [];
	}

	/**
	 * The innermost REST route being dispatched, or null outside route callbacks.
	 *
	 * @return array{shopper: bool, admin: bool, currency: ?string}|null
	 */
	public static function get_rest_route(): ?array {
		if ( [] === self::$rest_routes ) {
			return null;
		}

		$route = end( self::$rest_routes );
		unset( $route['request'] );

		return $route;
	}

	/**
	 * Whether the REST route being dispatched was requested by a user who can manage WooCommerce (for example the
	 * block editor or analytics) outside the Store API. False outside route callbacks.
	 *
	 * @return boolean
	 */
	public static function is_admin_rest_request(): bool {
		$route = self::get_rest_route();

		return null !== $route && ! $route['shopper'] && $route['admin'];
	}

	/**
	 * Whether the request may be shopper-facing: a frontend or Store API request, a frontend AJAX request, or a REST
	 * request. For REST requests the route decides: inside route callbacks only the Store API is, before them it is
	 * not known yet and the answer is yes, so the hooks a route may need are registered. Cron and admin requests are
	 * not.
	 *
	 * @param string|null $type Request context to check, or null for the current request's.
	 *
	 * @return boolean
	 */
	public static function is_frontend_request( ?string $type = null ): bool {
		$context = $type ?? self::get_request_context();

		switch ( $context ) {
			case 'frontend':
			case 'store_api':
				return true;
			case 'ajax':
				return ! is_admin();
			case 'rest':
				$route = null === $type ? self::get_rest_route() : null;
				return null === $route || $route['shopper'];
			default:
				return false;
		}
	}

	/**
	 * Whether a shipping method of the given class is calculating its rates, which WooCommerce reports as the
	 * `shipping.rates` operation with the method as its subject.
	 *
	 * @param string $method_class The shipping method's class.
	 *
	 * @return bool
	 */
	public function is_in_shipping_method_rates( string $method_class ): bool {
		return ActiveCurrency::get_subject_of( 'shipping.rates' ) instanceof $method_class;
	}

	/**
	 * Whether WooCommerce is operating on an order, such as re-applying a coupon to it.
	 *
	 * @return bool
	 */
	public static function is_in_order_operation(): bool {
		return ActiveCurrency::is_in_operation_prefix( 'order.' );
	}

	/**
	 * The currency a coupon's amount is held in, as WooCommerce reports it. Empty for the store currency.
	 *
	 * @param mixed $coupon The coupon object.
	 *
	 * @return string
	 */
	public static function get_coupon_amount_currency( $coupon ): string {
		return (string) $coupon->get_amount_currency();
	}

	/**
	 * Whether the request is a cron request.
	 *
	 * @param string|null $type Request context to check, or null for the current request's.
	 *
	 * @return bool
	 */
	public static function is_cron_request( ?string $type = null ): bool {
		return 'cron' === ( $type ?? self::get_request_context() );
	}

	/**
	 * Whether the request is not an admin or cron request. Unlike is_frontend_request(), REST requests from users who
	 * can manage WooCommerce count too.
	 *
	 * @return bool
	 */
	public static function is_non_admin_request(): bool {
		switch ( self::get_request_context() ) {
			case 'frontend':
			case 'store_api':
			case 'rest':
				return true;
			case 'ajax':
				return ! is_admin();
			default:
				return false;
		}
	}

	/**
	 * Writes the session into the client cookie.
	 *
	 * @param bool $set Should the session cookie be set.
	 *
	 * @return void
	 */
	public static function set_customer_session_cookie( bool $set ) {
		WC()->session->set_customer_session_cookie( $set );
	}

	/**
	 * Returns true if the request is a Store API request.
	 *
	 * @return bool
	 */
	public static function is_store_api_request(): bool {
		return 'store_api' === self::get_request_context();
	}
}
