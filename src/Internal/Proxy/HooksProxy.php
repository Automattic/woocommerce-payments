<?php
/**
 * Class ActionsProxy
 *
 * @package WooPayments
 */

namespace WooPayments\Internal\Proxy;

/**
 * A proxy for accessing actions and filters.
 *
 * This class should be used instead of accessing `add_filter` and
 * `add_action` directly, to make unit tests easier.
 *
 * In the future, we'd also like to add exceptions catching here.
 */
class HooksProxy {
	/**
	 * Retrieve the number of times an action is fired.
	 *
	 * @param string $tag The name of the action hook.
	 * @return int The number of times action hook $tag is fired.
	 */
	public function did_action( $tag ) {
		return did_action( $tag );
	}

	/**
	 * Calls the callback functions that have been added to a filter hook.
	 *
	 * @param string $tag     The name of the filter hook.
	 * @param mixed  $value   The value to filter.
	 * @param mixed  ...$parameters Additional parameters to pass to the callback functions.
	 * @return mixed The filtered value after all hooked functions are applied to it.
	 */
	public function apply_filters( $tag, $value, ...$parameters ) {
		return apply_filters( $tag, $value, ...$parameters );
	}
}
