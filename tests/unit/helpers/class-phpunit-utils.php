<?php
/**
 * General unit test helpers.
 *
 * @package WooCommerce/Tests
 */

/**
 * Class PHPUnit_Utils.
 */
class PHPUnit_Utils {

	/**
	 * Utility function to call a protected/private class method.
	 *
	 * @see https://stackoverflow.com/a/8702347
	 *
	 * @param  object $obj  The class instance.
	 * @param  string $name The name of the method to call.
	 * @param  array  $args The method arguments.
	 * @return mixed        The method return value.
	 */
	public static function call_method( $obj, $name, $args ) {
		$class  = new \ReflectionClass( $obj );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );

		return $method->invokeArgs( $obj, $args );
	}
}
