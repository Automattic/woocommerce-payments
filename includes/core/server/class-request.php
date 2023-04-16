<?php
/**
 * Class file for WCPay\Core\Server\Request.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server;

use DateTime;
use WC_Payments;
use WC_Payments_Http_Interface;
use WC_Payments_API_Client;
use WCPay\Core\Exceptions\Server\Request\Extend_Request_Exception;
use WCPay\Core\Exceptions\Server\Request\Immutable_Parameter_Exception;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Exceptions\API_Exception;
use WP_Error;

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
	 * @var []
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
	 * Holds the ID of an item, which is included in the request URL.
	 *
	 * @var mixed (int|string)
	 */
	protected $id;

	/**
	 * Creates a new request, loading dependencies in there.
	 *
	 * @param mixed $id The identifier for various update/get/delete requests.
	 *
	 * @return static
	 */
	public static function create( $id = null ) {
		return WC_Payments::create_request( static::class, $id );
	}

	/**
	 * Prevents the class from being constructed directly.
	 *
	 * @param WC_Payments_API_Client     $api_client The API client to use to send requests.
	 * @param WC_Payments_Http_Interface $http_interface The HTTP interface for the server.
	 * @param mixed                      $id An optional ID for the item that will be updated/retrieved/deleted.
	 *
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payments_Http_Interface $http_interface, $id = null ) {
		$this->api_client     = $api_client;
		$this->http_interface = $http_interface;

		if ( method_exists( $this, 'set_id' ) ) {
			if ( null !== $id ) {
				$this->set_id( $id );
			} else {
				throw new Invalid_Request_Parameter_Exception( 'This request requires an item ID.', 'wcpay_core_invalid_request_parameter_missing_id' );
			}
		}
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
	 * @throws Invalid_Request_Parameter_Exception If the request has not been initialized yet.
	 */
	final public function get_params() {
		$defaults = static::get_default_params();
		$params   = array_merge( $defaults, $this->params );

		$missing_params = [];
		foreach ( static::get_required_params() as $name ) {
			if ( ! isset( $params[ $name ] ) ) {
				$missing_params[] = $name;
			}
		}

		if ( ! empty( $missing_params ) ) {
			throw new Invalid_Request_Parameter_Exception(
				sprintf(
					'Trying to access the parameters of a request which is not (fully) initialized yet. Missing parameter(s) for %s: %s',
					get_class( $this ),
					implode( ', ', $missing_params )
				),
				'wcpay_core_invalid_request_parameter_missing_parameters'
			);
		}

		foreach ( $params as $key => $value ) {
			if ( true === $value ) {
				// The WCPay server requires the string 'true'.
				$params[ $key ] = 'true';
			}
		}

		return $params;
	}

	/**
	 * Get request param by key.
	 *
	 * @param string $key Key to get.
	 *
	 * @return mixed
	 * @throws Invalid_Request_Parameter_Exception
	 */
	final public function get_param( $key ) {
		if ( array_key_exists( $key, $this->params ) ) {
			return $this->params[ $key ];
		}
		throw new Invalid_Request_Parameter_Exception(
			sprintf(
				'The passed key %s does not exist in Request class',
				$key
			),
			'wcpay_core_invalid_request_parameter_uninitialized_param'
		);
	}

	/**
	 * Allows the request to be modified, and then sends it.
	 *
	 * @param string $hook    The filter to use.
	 * @param mixed  ...$args      Other parameters for the hook.
	 * @return mixed               Either the response array, or the correct object.
	 *
	 * @throws Extend_Request_Exception
	 * @throws Immutable_Parameter_Exception
	 * @throws Invalid_Request_Parameter_Exception
	 */
	final public function send( $hook, ...$args ) {
		return $this->format_response(
			$this->api_client->send_request( $this->apply_filters( $hook, ...$args ) )
		);
	}

	/**
	 * This is mimic of send method, but where API execption is handled.
	 * The reason behind this is that sometimes API request can fail for valid reasons and instead of handling this exception on every request, you could use this function.
	 *
	 * @param string $hook         The filter to use.
	 * @param mixed  ...$args      Other parameters for the hook.
	 * @return mixed               Either the response array, or the correct object.
	 *
	 * @throws Extend_Request_Exception
	 * @throws Immutable_Parameter_Exception
	 * @throws Invalid_Request_Parameter_Exception
	 */
	final public function handle_rest_request( $hook, ...$args ) {
		try {
			$data = $this->send( $hook, ...$args );
			// Make sure to return array if $data is instance or has parent as a Response class.
			if ( is_a( $data, Response::class ) ) {
				return $data->to_array();
			}

			// Return the data and let caller to parse it as it pleases.
			return $data;
		} catch ( API_Exception $e ) {
			return new WP_Error( $e->get_error_code(), $e->getMessage() );
		}
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
		if ( $this->protected_mode && in_array( $key, static::get_immutable_params(), true ) ) {
			$this->throw_immutable_exception( $key );
		}

		$this->params[ $key ] = $value;
	}

	/**
	 * Unsets an existing parameter if it was set before.
	 *
	 * @param string $key The key of the parameter.
	 */
	final protected function unset_param( string $key ) {
		if ( $this->protected_mode && in_array( $key, static::get_immutable_params(), true ) ) {
			$this->throw_immutable_exception( $key );
		}

		if ( isset( $this->params[ $key ] ) ) {
			unset( $this->params[ $key ] );
		}
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
	 * @param  Request $base_request    The request to extend.
	 * @return static                   An instance of the class.
	 * @throws Extend_Request_Exception In case this is not a subclass of the base request.
	 */
	final public static function extend( Request $base_request ) {
		$current_class = static::class;
		$base_request->validate_extended_class( $current_class, get_class( $base_request ) );

		if ( ! $base_request->protected_mode ) {
			throw new Extend_Request_Exception(
				get_class( $base_request ) . ' can only be extended within its ->apply_filters() method.',
				'wcpay_core_extend_class_incorrectly'
			);
		}
		$obj = new $current_class( $base_request->api_client, $base_request->http_interface );
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
	 * @param string $hook                             The filter to use.
	 * @param mixed  ...$args                          Other parameters for the hook.
	 * @return static                                  Either the same instance, or an object from a sub-class.
	 * @throws Extend_Request_Exception                In case a class does not exist.
	 * @throws Immutable_Parameter_Exception           In case an immutable propery is tried to change.
	 * @throws Invalid_Request_Parameter_Exception     In case an invalid property is passed.
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

		$my_class  = get_class( $this );
		$new_class = get_class( $replacement );
		if ( $new_class !== $my_class ) {
			$this->validate_extended_class( $new_class, $my_class );
		}

		// NB: `array_diff` will only pick up updated props, not new ones.
		$difference = $this->array_diff( $this->params, $replacement->params );

		if ( empty( $difference ) ) {
			// Nothing got overwritten, it's the same request, or one with only new props.
			return $replacement;
		}

		foreach ( static::get_immutable_params() as $param ) {
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
	 * @throws Immutable_Parameter_Exception   An exception, which indicates which property is immutable.
	 */
	private function throw_immutable_exception( string $param ) {
		throw new Immutable_Parameter_Exception(
			sprintf(
				'The value of %s::%s is immutable and cannot be changed.',
				get_class( $this ),
				$param
			),
			'wcpay_core_immutable_parameter_changed'
		);
	}

	/**
	 * Returns an array with the names of params, which should not be modified.
	 *
	 * @return string[] The names of those params.
	 */
	public static function get_immutable_params() {
		return static::traverse_class_constants( 'IMMUTABLE_PARAMS', true );
	}

	/**
	 * Returns an array with the names of params, which are required.
	 *
	 * @return string[] The names of those params.
	 */
	public static function get_required_params() {
		return static::traverse_class_constants( 'REQUIRED_PARAMS', true );
	}

	/**
	 * Returns an array with the combined default params from all classes.
	 */
	public static function get_default_params() {
		return static::traverse_class_constants( 'DEFAULT_PARAMS' );
	}

	/**
	 * Combines array constants from a class's tree.
	 *
	 * @param  string $constant_name The name of the constant to load.
	 * @param  bool   $unique        Whether to return unique items. Useful with numeric keys.
	 * @return string[] The unique combined values from the arrays.
	 */
	public static function traverse_class_constants( string $constant_name, bool $unique = false ) {
		$keys       = [];
		$class_name = static::class;

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

	/**
	 * Validates Stripe identifiers.
	 *
	 * @param  string     $id        The identifier to validate.
	 * @param  mixed|null $prefixes  A prefix or an array of them (Optional).
	 * @throws Invalid_Request_Parameter_Exception An exception if the format is not matched.
	 * @return void
	 */
	protected function validate_stripe_id( $id, $prefixes = null ) {
		if ( empty( $id ) ) {
			throw new Invalid_Request_Parameter_Exception(
				__( 'Empty parameter is not allowed', 'woocommerce-payments' ),
				'wcpay_core_invalid_request_parameter_stripe_id'
			);
		}
		if ( is_null( $prefixes ) ) {
			$prefixes = '[a-z]+';
		} else {
			if ( ! is_array( $prefixes ) ) {
				$prefixes = [ $prefixes ];
			}

			$prefixes = '('
				. implode( '|', array_map( 'preg_quote', $prefixes ) )
				. ')';
		}

		/**
		 * IDs include a prefix (a few characters), and are up to 255 characters long.
		 *
		 * @see https://stripe.com/docs/upgrades#what-changes-does-stripe-consider-to-be-backwards-compatible
		 */
		$regex = "/^{$prefixes}_\w{1,250}$/";

		if ( preg_match( $regex, $id ) ) {
			return;
		}

		throw new Invalid_Request_Parameter_Exception(
			sprintf(
				// Translators: %s is a Stripe ID.
				__( '%s is not a valid Stripe identifier', 'woocommerce-payments' ),
				$id
			),
			'wcpay_core_invalid_request_parameter_stripe_id'
		);
	}

	/**
	 * Validate is number larger than passed compared number.
	 *
	 * @param  float $value_to_validate Value to validate.
	 * @param  float $value_to_compare Value to compare.
	 * @throws Invalid_Request_Parameter_Exception An exception if the format is not matched.
	 * @return void
	 */
	protected function validate_is_larger_then( float $value_to_validate, float $value_to_compare ) {
		if ( $value_to_validate > $value_to_compare ) {
			return;
		}

		throw new Invalid_Request_Parameter_Exception(
			"Invalid number passed. Number $value_to_compare needs to be larger than $value_to_compare",
			'wcpay_core_invalid_request_parameter_order'
		);
	}

	/**
	 * Currency code validator.
	 *
	 * @param string $currency_code Currency code.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function validate_currency_code( string $currency_code ) {
		$account_data = WC_Payments::get_account_service()->get_cached_account_data();
		if ( isset( $account_data['customer_currencies']['supported'] ) && ! in_array( $currency_code, $account_data['customer_currencies']['supported'], true ) ) {
			throw new Invalid_Request_Parameter_Exception(
				sprintf(
				// Translators: %s is a currency code.
					__( '%s is not a supported currency for payments.', 'woocommerce-payments' ),
					$currency_code
				),
				'wcpay_core_invalid_request_parameter_currency_not_available'
			);
		}
	}

	/**
	 * Extend class validator.
	 *
	 * @param mixed  $child_class  Child class.
	 * @param string $parent_class Parent class.
	 *
	 * @return void
	 * @throws Extend_Request_Exception
	 */
	public function validate_extended_class( $child_class, string $parent_class ) {

		if ( ! is_subclass_of( $child_class, $parent_class ) ) {
			throw new Extend_Request_Exception(
				sprintf(
					'Failed to extend request. %s is not a subclass of %s',
					is_string( $child_class ) ? $child_class : get_class( $child_class ),
					$parent_class
				),
				'wcpay_core_extend_class_not_subclass'
			);
		}

	}

	/**
	 * Validate date with given format.
	 *
	 * @param string $date Date to validate.
	 * @param string $format Format to check.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function validate_date( string $date, string $format = 'Y-m-d H:i:s' ) {
		$d = DateTime::createFromFormat( $format, $date );
		if ( ! ( $d && $d->format( $format ) === $date ) ) {
			throw new Invalid_Request_Parameter_Exception(
				sprintf(
					// Translators: %1$s is a provided date string, %2$s is a date format.
					__( '%1$s is not a valid date for format %2$s.', 'woocommerce-payments' ),
					$date,
					$format
				),
				'wcpay_core_invalid_request_parameter_invalid_date'
			);
		}
	}

	/**
	 * Validate a redirect URL in the allowed_redirect_hosts filter.
	 *
	 * @param  string $redirect_url The provided redirect URL.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function validate_redirect_url( string $redirect_url ) {
		$check_fallback_url = wp_generate_password( 12, false );
		if ( hash_equals( $check_fallback_url, wp_validate_redirect( $redirect_url, $check_fallback_url ) ) ) {
			throw new Invalid_Request_Parameter_Exception(
				sprintf(
				// Translators: %s is a currency code.
					__( '%1$s is not a valid redirect URL. Use a URL in the allowed_redirect_hosts filter.', 'woocommerce-payments' ),
					$redirect_url
				),
				'wcpay_core_invalid_request_parameter_invalid_redirect_url'
			);
		}
	}

	/**
	 * Validate if the username exists and is valid on the site.
	 *
	 * @param string $user_name Username to validate.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function validate_user_name( string $user_name ) {
		$user = get_user_by( 'login', $user_name );
		if ( false === $user ) {
			throw new Invalid_Request_Parameter_Exception(
				sprintf(
					// Translators: %s is a provided username.
					__( '%s is not a valid username.', 'woocommerce-payments' ),
					$user_name
				),
				'wcpay_core_invalid_request_parameter_invalid_username'
			);
		}
	}
}
