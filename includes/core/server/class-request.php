<?php
/**
 * Class file for WCPay\Core\Server\Request.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server;

use Exception;
use WC_Payments_Http_Interface;
use WC_Payments_API_Client;

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
	 * Indicates which parameters are required (keys only).
	 *
	 * @var string[]
	 */
	const REQUIRED_PARAMS = [];

	/**
	 * Contains default values for parameters, which are not set automatically.
	 *
	 * @var string[]
	 */
	const DEFAULT_PARAMS = [];

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
	 * Holds the API client of WCPay.
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $api_client;

	/**
	 * Holds the HTTP interface of WCPay.
	 *
	 * @var WC_Payments_Http_Interface
	 */
	protected $http_interface;

	/**
	 * Prevents the class from being constructed directly.
	 *
	 * @param WC_Payments_API_Client     $api_client     The API client to use to send requests.
	 * @param WC_Payments_Http_Interface $http_interface The HTTP interface for the server.
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payments_Http_Interface $http_interface ) {
		$this->api_client     = $api_client;
		$this->http_interface = $http_interface;
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
		$defaults = $this->get_default_params();
		$params   = array_merge( $defaults, $this->params );

		$missing_params = [];
		foreach ( $this->get_required_params() as $name ) {
			if ( ! isset( $params[ $name ] ) ) {
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

		foreach ( $params as $key => $value ) {
			if ( is_bool( $value ) ) {
				// The WCPay server requires the string 'true'.
				$params[ $key ] = $value ? 'true' : false;
			}
		}

		return $params;
	}

	/**
	 * Allows the request to be modified, and then sends it.
	 *
	 * @param string $hook    The filter to use.
	 * @param mixed  ...$args Other parameters for the hook.
	 * @return mixed          Either the response array, or the correct object.
	 */
	final public function send( $hook, ...$args ) {
		$request = $this->apply_filters( $hook, ...$args );

		return $this->format_response(
			$this->api_client->send_request( $request )
		);
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
	 * @param  string $key   The name of the parameter.
	 * @param  mixed  $value And the value to set.
	 * @return static        The instance of the class, ready for method chaining.
	 */
	final protected function set_param( string $key, $value ) {
		if ( $this->protected_mode && in_array( $key, $this->get_immutable_params(), true ) ) {
			$this->throw_immutable_exception( $key );
		}

		$this->params[ $key ] = $value;

		return $this;
	}

	/**
	 * Unsets an existing parameter if it was set before.
	 *
	 * @param  string $key The key of the parameter.
	 * @return static      The instance of the class for method chaining.
	 */
	final protected function unset_param( string $key ) {
		if ( $this->protected_mode && in_array( $key, $this->get_immutable_params(), true ) ) {
			$this->throw_immutable_exception( $key );
		}

		if ( isset( $this->params[ $key ] ) ) {
			unset( $this->params[ $key ] );
		}

		return $this;
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
	 * Creates a new instance of the called class with the same props
	 * as an existing request, which must be of a parent class.
	 *
	 * This method is only available within `apply_filters()`.
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

		$obj = new static( $base_request->api_client, $base_request->http_interface );
		$obj->set_params( $base_request->params );

		return $obj;
	}

	/**
	 * Allows the request to be altered/replaced through a filter.
	 *
	 * Call this method when the request has been completely prepared,
	 * and is ready to be sent to the server. At this point functions,
	 * which hook into the filter cannot alter the IMMUTABLE_PARAMS
	 * of the request anymore. Instead they can either modify the other
	 * mutable params, or extend the request.
	 *
	 * @param string $hook    The filter to use.
	 * @param mixed  ...$args Other parameters for the hook.
	 * @return static         Either the same instance, or an object from a sub-class.
	 * @throws \Exception     In case a class does not exists, or immutable properties are modified.
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
		$difference = $this->array_diff( $this->get_params(), $replacement->get_params() );
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
		return $this->traverse_class_constants( 'IMMUTABLE_PARAMS', true );
	}

	/**
	 * Returns an array with the names of params, which are required.
	 *
	 * @return string[] The names of those params.
	 */
	private function get_required_params() {
		return $this->traverse_class_constants( 'REQUIRED_PARAMS', true );
	}

	/**
	 * Returns an array with the combined default params from all classes.
	 */
	private function get_default_params() {
		return $this->traverse_class_constants( 'DEFAULT_PARAMS' );
	}

	/**
	 * Combines array constants from a class's tree.
	 *
	 * @param  string $constant_name The name of the constant to load.
	 * @param  bool   $unique        Whether to return unique items. Useful with numeric keys.
	 * @return string[] The unique combined values from the arrays.
	 */
	private function traverse_class_constants( string $constant_name, bool $unique = false ) {
		$keys       = [];
		$class_name = get_class( $this );

		do {
			$constant = "$class_name::$constant_name";

			if ( defined( $constant ) ) {
				$keys = array_merge( $keys, constant( $constant ) );
			}

			$class_name = get_parent_class( $class_name );
		} while ( $class_name );

		if ( $unique ) {
			$keys = array_unique( $keys );
		}

		return $keys;
	}

	/**
	 * Generates the difference between two arrays.
	 *
	 * @param array $array1 The first array.
	 * @param array $array2 The second array.
	 * @return array        The difference between the two arrays.
	 */
	private function array_diff( $array1, $array2 ) {
		$arr_to_json = function( $item ) {
			return is_array( $item ) ? wp_json_encode( $item ) : $item;
		};

		return array_diff_assoc(
			array_map( $arr_to_json, $array1 ),
			array_map( $arr_to_json, $array2 )
		);
	}
}
