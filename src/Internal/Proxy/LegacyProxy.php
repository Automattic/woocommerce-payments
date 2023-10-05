<?php
/**
 * Class LegacyProxy
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Proxy;

/**
 * Legacy Proxy
 *
 * Used for accessing legacy code (everything outside `src`), incl. functions, static methods, and globals.
 * Classes are handled through WCPay\Internal\DependencyManagement\DelegateContainer\LegacyContainer.
 */
class LegacyProxy {
	/**
	 * Calls a function outside of `src`.
	 *
	 * Use this for WP, WC, and other generic non-native PHP functions.
	 *
	 * @param string $name          Name of the function.
	 * @param mixed  ...$parameters Parameters to pass to the function.
	 *
	 * @return mixed The response from the function.
	 */
	public function call_function( string $name, ...$parameters ) {
		return call_user_func_array( $name, $parameters );
	}

	/**
	 * Calls the static method of a class outside of `src`.
	 *
	 * Use this for non-`src` classes.
	 * Static methods on `src` classes should be pure (without side effects).
	 *
	 * @param string $class_name    Name of the class.
	 * @param string $method_name   Name of the method.
	 * @param mixed  ...$parameters Parameters to pass to the method.
	 *
	 * @return mixed The response from the method.
	 */
	public function call_static( string $class_name, string $method_name, ...$parameters ) {
		return call_user_func_array( [ $class_name, $method_name ], $parameters );
	}

	/**
	 * Checks whether a global variable is defined.
	 *
	 * @param string $name Name of the variable.
	 * @return bool
	 */
	public function has_global( string $name ) {
		return isset( $GLOBALS[ $name ] );
	}

	/**
	 * Returns a global variable.
	 *
	 * @param string $name    Name of the variable.
	 * @throws ProxyException In case the variable is not set.
	 * @return mixed
	 */
	public function get_global( string $name ) {
		if ( ! $this->has_global( $name ) ) {
			throw new ProxyException( sprintf( 'The global "%s" is not set.', $name ) );
		}

		return $GLOBALS[ $name ];
	}
}
