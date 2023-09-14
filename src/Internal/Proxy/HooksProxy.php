<?php
/**
 * Class HooksProxy
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Proxy;

/**
 * Hooks Proxy
 *
 * Used for accessing WP hooks, incl. actions and filters.
 *
 * This class currently only contains methods to add filters and actions.
 * If you need any other hook-related methods (ex. `do_action`, `apply_filters`),
 * etc., feel free to add them to the class.
 */
class HooksProxy {
	/**
	 * Add a filter.
	 *
	 * @param string   $hook_name     The name of the filter to add the callback to.
	 * @param callable $callback      The callback to be run when the filter is applied.
	 * @param int      $priority      Priority amongs other callbacks.
	 *                                Low = early, high = late. Optional. Default 10.
	 * @param int      $accepted_args The number of arguments the callback accepts. Optional. Default 1.
	 */
	public function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		add_filter( $hook_name, $callback, $priority, $accepted_args );
	}

	/**
	 * Add an action.
	 *
	 * @param string   $hook_name     The name of the action to add the callback to.
	 * @param callable $callback      The callback to be run when the filter is applied.
	 * @param int      $priority      Priority amongs other callbacks.
	 *                                Low = early, high = late. Optional. Default 10.
	 * @param int      $accepted_args The number of arguments the callback accepts. Optional. Default 1.
	 */
	public function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		add_action( $hook_name, $callback, $priority, $accepted_args );
	}
}
