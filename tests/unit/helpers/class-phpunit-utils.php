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

	/**
	 * Utility function to that supplies a spy function.
	 *
	 * @return object An object that handles spying on a function.
	 */
	public static function function_spy() {
		$the_args = null;
		$fn       = function ( ...$args ) use ( &$the_args ) {
			$the_args = $args;

			return $args;
		};

		return (object) [
			'fn'            => $fn,
			'computed_fn'   => function ( $compute_fn ) use ( $fn ) {
				return function ( ...$args ) use ( $fn, $compute_fn ) {
					return $compute_fn( ...$fn( ...$args ) );
				};
			},
			'received_args' => function () use ( &$the_args ) {
				return $the_args;
			},
			'received_arg'  => function ( $n ) use ( &$the_args ) {
				return $the_args[ $n ];
			},
		];
	}
}
