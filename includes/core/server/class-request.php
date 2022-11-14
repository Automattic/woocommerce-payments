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
	 * True when `->apply_filters()` is called to protect read-only props.
	 *
	 * In protected mode, if somebody tries to change an immutable param,
	 * as declared in `IMMUTABLE_PARAMS`, an exception will be thrown.
	 *
	 * This way important params can be safe from modifications by extensions.
	 *
	 * @var bool
	 */
	private $protected_mode = false;

	/**
	 * Creates a new instance of the class.
	 *
	 * @return static
	 */
	public static function create() {
		return new static();
	}

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
	 * @throws \Exception If the request has not been initialized yet.
	 */
	final public function get_params() {
		$missing_params = [];
		foreach ( $this->get_required_params() as $name ) {
			if ( ! isset( $this->params[ $name ] ) ) {
				$missing_params[] = $name;
			}
		}

		if ( ! empty( $missing_params ) ) {
			throw new \Exception(
				sprintf(
					'Trying to access the parameters of a request which is not (fully) initialized yet. Missing parameter(s) for %s: %s',
					get_class( $this ),
					implode( ', ', $missing_params )
				)
			);
		}

		return $this->params;
	}

	/**
	 * Formats the response from the server.
	 *
	 * @param  mixed $response The response from `WC_Payments_API_Client::request`.
	 * @return mixed           Either the same response, or the correct object.
	 */
	public function format_response( $response ) {
		return new Response( $response );
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
	final protected function set_param( string $key, $value ) {
		if ( $this->protected_mode && in_array( $key, $this->get_immutable_params(), true ) ) {
			$this->throw_immutable_exception( $key );
		}

		$this->params[ $key ] = $value;
	}

	/**
	 * Replaces all internal parameters of the class.
	 * Only accessible from methods of this class, used for the `extend` method.
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

		if ( ! $base_request->protected_mode ) {
			throw new \Exception( get_class( $base_request ) . ' can only be extended within its ->apply_filters() method.' );
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
		// Lock the class in order to prevent `set_param` for protected props.
		$this->protected_mode = true;

		/**
		 * Allows a request to be modified, extended or replaced.
		 *
		 * @param Request $request The request to modify.
		 * @param mixed   ...$args Other provided parameters for the hook.
		 * @return Request         Either the same request, or a sub-class.
		 */
		$replacement = apply_filters( $hook, $this, ...$args );

		// Exit protected mode right after `apply_filters`.
		$this->protected_mode = false;

		// Make sure the replacement is either the same class, or a sub-class.
		$my_class  = get_class( $this );
		$new_class = get_class( $replacement );
		if ( $new_class !== $my_class && ! is_subclass_of( $replacement, $my_class ) ) {
			throw new \Exception(
				sprintf(
					'Failed to modify request. The provided %s is not a subclass of %s',
					$new_class,
					$my_class
				)
			);
		}

		// NB: `array_diff` will only pick up updated props, not new ones.
		$difference = array_diff( $this->get_params(), $replacement->get_params() );
		if ( empty( $difference ) ) {
			// Nothing got overwritten, it's the same request, or one with only new props.
			return $replacement;
		}

		foreach ( $this->get_immutable_params() as $param ) {
			if ( isset( $difference[ $param ] ) ) {
				$this->throw_immutable_exception( $param );
			}
		}

		return $replacement;
	}

	/**
	 * Throws an exception upon attempts to mutate an immutable parameter.
	 *
	 * @param string $param The name of the param.
	 * @throws \Exception   An exception, which indicates which property is immutable.
	 */
	private function throw_immutable_exception( string $param ) {
		throw new \Exception(
			sprintf(
				'The value of %s::%s is immutable and cannot be changed.',
				get_class( $this ),
				$param
			)
		);
	}

	/**
	 * Returns an array with the names of params, which should not be modified.
	 *
	 * @return string[] The names of those params.
	 */
	private function get_immutable_params() {
		return $this->traverse_class_constants( 'IMMUTABLE_PARAMS' );
	}

	/**
	 * Returns an array with the names of params, which are required.
	 *
	 * @return string[] The names of those params.
	 */
	private function get_required_params() {
		return $this->traverse_class_constants( 'REQUIRED_PARAMS' );
	}

	/**
	 * Combines array constants from a class's tree.
	 *
	 * @param  string $constant_name The name of the constant to load.
	 * @return string[] The unique combined values from the arrays.
	 */
	private function traverse_class_constants( string $constant_name ) {
		$immutable  = [];
		$class_name = get_class( $this );

		do {
			$constant = "$class_name::$constant_name";

			if ( defined( $constant ) ) {
				$immutable = array_merge( $immutable, constant( $constant ) );
			}

			$class_name = get_parent_class( $class_name );
		} while ( $class_name );

		return array_unique( $immutable );
	}
}
