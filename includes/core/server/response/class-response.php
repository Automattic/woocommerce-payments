<?php
/**
 * Response DTO.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\Server;

use ReflectionClass;
use WCPay\Core\Contracts\API\Response\Base_Response_Interface;
use WP_Http_Cookie;

/**
 * DTO for parsing response from WCPay server API.
 */
class Response implements Base_Response_Interface {

	/**
	 * Status code.
	 *
	 * @var int $code
	 */
	private $code;

	/**
	 * Response headers.
	 *
	 * @var array $headers
	 */
	private $headers;

	/**
	 * Response message.
	 *
	 * @var string $message
	 */
	private $message;

	/**
	 * Response cookies.
	 *
	 * @var WP_Http_Cookie[] $cookies
	 */
	private $cookies;

	/**
	 * Parsed response array.
	 *
	 * @var array $response_dody
	 */
	private $response_data;

	/**
	 * Class constructor.
	 *
	 * @param string           $body Response body.
	 * @param int              $code Response code.
	 * @param array            $headers Response headers.
	 * @param string           $message Response message.
	 * @param WP_Http_Cookie[] $cookies Response cookies.
	 */
	public function __construct( $body, $code, $headers, $message, $cookies ) {
		$this->code          = $code;
		$this->headers       = $headers;
		$this->message       = $message;
		$this->cookies       = $cookies;
		$this->response_data = json_decode( $body, true );
	}

	/**
	 * Get statzs code.
	 *
	 * @return int
	 */
	public function get_code() {
		return $this->code;
	}

	/**
	 * Get headers.
	 *
	 * @return array
	 */
	public function get_headers() {
		return $this->headers;
	}

	/**
	 * Get message.
	 *
	 * @return string
	 */
	public function get_message() {
		return $this->message;
	}

	/**
	 * Get cookies
	 *
	 * @return WP_Http_Cookie[]
	 */
	public function get_cookies() {
		return $this->cookies;
	}

	/**
	 * Get response body as array.
	 *
	 * @return array|mixed
	 */
	public function data() {
		$data = $this->response_data;
		// Make sure empty metadata serialized on the client as an empty object {} rather than array [].
		if ( isset( $data['metadata'] ) && empty( $data['metadata'] ) ) {
			$data['metadata'] = new \stdClass();
		}
		return $data;
	}

	/**
	 * Create DTO from WCPay response.
	 *
	 * @param array $response Server response.
	 * @return Response
	 */
	public static function create_from_wc_pay_server_response( $response ) {
		$body    = wp_remote_retrieve_body( $response );
		$code    = wp_remote_retrieve_response_code( $response ) ?? 0;
		$headers = wp_remote_retrieve_headers( $response );
		$message = wp_remote_retrieve_response_message( $response );
		$cookies = wp_remote_retrieve_cookies( $response );
		return new self( $body, $code, $headers, $message, $cookies );
	}

	/**
	 * Return array of all properties.
	 *
	 * @return array
	 */
	public function to_array() {
		$class = new ReflectionClass( $this );
		$data  = [];
		foreach ( $class->getProperties() as $reflectionProperty ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			$data[ $reflectionProperty->getName() ] = $reflectionProperty->getValue(); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		}

		return $data;
	}



}
