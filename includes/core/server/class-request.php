<?php
/**
 * Class file for WCPay\Core\Server\Request.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server;

use Exception;

/**
 * Base for requests to the WCPay server.
 */
abstract class Request {
	/**
	 * Contains a set of params, which the class considers immutable by others.
	 *
	 * Overwrite this in your class for individual properties.
	 *
	 * @var string[]
	 */
	const IMMUTABLE_PARAMS = [];

	/**
	 * Holds the parameters of the request.
	 *
	 * @var mixed[]
	 */
	private $params = [];

	/**
	 * Prevents the class from being constructed directly.
	 */
	protected function __construct() {
		// Nothing to do here yet.
	}

	/**
	 * Returns the needed API.
	 *
	 * @return string Check WCPay\Core\Server\APIs.
	 */
	abstract public function get_api(): string;

	/**
	 * Returns the method of the request.
	 *
	 * @return string See the constants in WordPress's `Requests` class.
	 */
	abstract public function get_method(): string;

	/**
	 * This is a legacy method, and is the same throughout the codebase.
	 * Might be worth removing while refactoring to use the Core\Server API.
	 *
	 * @return bool
	 */
	public function is_site_specific(): bool {
		return true;
	}

	/**
	 * If true, the request will be signed with the user token rather than blog token. Defaults to false.
	 *
	 * @return bool
	 */
	public function should_use_user_token(): bool {
		return false;
	}

	/**
	 * Indicates if the raw response should be returned.
	 *
	 * @return bool
	 */
	public function should_return_raw_response(): bool {
		return false;
	}

	/**
	 * Returns all of the parameters for the request.
	 *
	 * @return array
	 */
	public function get_params() {
		return $this->params;
	}

	/**
	 * Stores a parameter within the internal props.
	 *
	 * Use this method within child classes in order to allow
	 * those properties to be protected by overwriting.
	 *
	 * @param string $key   The name of the parameter.
	 * @param mixed  $value And the value to set.
	 */
	protected function set_param( string $key, $value ) {
		$this->params[ $key ] = $value;
	}

	/**
	 * Replaces all internal parameters of the class.
	 * Only accessible from this class, this method is used for `extend`.
	 *
	 * @param array $params The new parameters to use.
	 */
	private function set_params( $params ) {
		$this->params = $params;
	}

	/**
	 * Creates a new instance the class with the same props as the parent.
	 *
	 * @param  Request $base_request The request to extend.
	 * @return static                An instance of the class.
	 * @throws \Exception            In case this is not a subclass of the base request.
	 */
	final public static function extend( Request $base_request ) {
		if ( ! is_subclass_of( static::class, get_class( $base_request ) ) ) {
			throw new \Exception(
				sprintf(
					'Failed to extend request. %s is not a subclass of %s',
					static::class,
					get_class( $base_request )
				)
			);
		}

		$obj = new static();
		$obj->set_params( $base_request->params );

		return $obj;
	}

	/**
	 * Allows the request to be changed via a hook.
	 *
	 * Supposedly this method will verify the protected params of parents.
	 *
	 * @param string $hook    The filter to use.
	 * @param mixed  ...$args Other parameters for the hook.
	 * @return static         Either the same instance, or an object from a sub-class.
	 * @throws \Exception     In case a class does not exists, or immutable properties are modified.
	 * @todo                  Add proper exceptions here.
	 */
	final public function apply_filters( $hook, ...$args ) {
		$cloned = clone $this; // Work with a clone to avoid mutations of parameters.

		/**
		 * Allows a request to be modified, extended or replaced.
		 *
		 * @param Request $request The request to modify.
		 * @param mixed   ...$args Other provided parameters for the hook.
		 * @return Request         Either the same request, or a sub-class.
		 */
		$replacement = apply_filters( $hook, $cloned, ...$args );

		// Make sure the replacement is either the same class, or a sub-class.
		if ( get_class( $replacement ) !== get_class( $this ) && ! is_subclass_of( $replacement, get_class( $this ) ) ) {
			throw new \Exception(
				sprintf(
					'Failed to modify request. The provided %s is not a subclass of %s',
					get_class( $replacement ),
					get_class( $this )
				)
			);
		}

		if ( $replacement->get_params() === $this->get_params() ) {
			// Nothing was replaced, nothing to check.
			return $this;
		}

		// NB: `array_diff` will only pick up updated props, not new ones.
		$difference = array_diff( $this->get_params(), $replacement->get_params() );
		if ( empty( $difference ) ) {
			// Nothing got overwritten, it's the same request, or one with only new props.
			return $replacement;
		}

		foreach ( $this->get_immutable_params() as $param ) {
			if ( isset( $difference[ $param ] ) ) {
				throw new \Exception(
					sprintf(
						'The value of %s::%s is immutable and cannot be changed.',
						get_class( $this ),
						$param
					)
				);
			}
		}

		return $replacement;
	}

	/**
	 * Returns an array with the names of params, which should not be modified.
	 *
	 * @return string[] The names of those params.
	 */
	private function get_immutable_params() {
		$immutable  = [];
		$class_name = get_class( $this );

		do {
			$immutable  = array_merge( $immutable, $class_name::IMMUTABLE_PARAMS );
			$class_name = get_parent_class( $class_name );
		} while ( $class_name );

		return array_unique( $immutable );
	}
}
