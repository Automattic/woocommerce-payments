<?php
/**
 * Response DTO.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\DataTransferObjects;

use WCPay\Core\Contracts\API\Response\Base_Response;
use WP_Http_Cookie;

/**
 * DTO for parsing response from WCPay server API.
 */
final class Response extends Data_Transfer_Object implements Base_Response {
	/**
	 * Response body.
	 *
	 * @var string $body
	 */
	private $body;

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
		$this->body          = $body;
		$this->code          = $code;
		$this->headers       = $headers;
		$this->message       = $message;
		$this->cookies       = $cookies;
		$this->response_data = json_decode( $body, true );
	}

	/**
	 * Returns true if response is JSON.
	 *
	 * @return bool
	 */
	public function is_json_response() {
		return 'application/json' === substr( $this->headers['content-type'] ?? '', 0, strlen( 'application/json' ) );
	}

	/**
	 * Get body.
	 *
	 * @return string
	 */
	public function get_body() {
		return $this->body;
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
	public function get_response_data() {
		$data = $this->response_data;
		// Make sure empty metadata serialized on the client as an empty object {} rather than array [].
		if ( isset( $data['metadata'] ) && empty( $data['metadata'] ) ) {
			$data['metadata'] = new \stdClass();
		}
		return $data;
	}

	/**
	 * Check if response is error one.
	 *
	 * @return bool
	 */
	public function is_error_response() {
		return 400 <= $this->code;
	}


	/**
	 * Get response message.
	 *
	 * @return mixed|null
	 */
	public function get_response_message() {
		return $this->response_data['message'] ?? null;
	}

	/**
	 * Get code from response data.
	 *
	 * @return mixed|null
	 */
	public function get_code_from_response_data() {
		return $this->response_data['code'] ?? null;
	}

	/**
	 * Get value from response by key.
	 *
	 * @param string $parameter Parameter in array.
	 * @param string $key Key in array.
	 * @return mixed|null
	 */
	public function get_value_from_response_by_key( $parameter, $key = 'data' ) {
		return $this->response_data[ $key ][ $parameter ] ?? null;
	}

	/**
	 * Get error code.
	 *
	 * @return mixed|null
	 */
	public function get_error_code() {
		return $this->response_data['error']['code'] ?? null;
	}

	/**
	 * Get error type
	 *
	 * @return mixed|null
	 */
	public function get_error_type() {
		return $this->response_data['error']['type'] ?? null;
	}

	/**
	 * Get error message.
	 *
	 * @return mixed|null
	 */
	public function get_error_message() {
		return $this->response_data['error']['message'] ?? null;
	}

	/**
	 * Determinate does code exists in response data.
	 *
	 * @return bool
	 */
	public function has_code_in_response_data() {
		return isset( $this->response_data['code'] );
	}

	/**
	 * Determinate does error exists in response data.
	 *
	 * @return bool
	 */
	public function has_error_in_response_data() {
		return isset( $this->response_data['error'] );
	}

	/**
	 * Determinate is amount to small error presented.
	 *
	 * @return bool
	 */
	public function has_amount_too_small_error_code() {
		return $this->has_code_in_response_data() && 'amount_too_small' === $this->response_data['code'];
	}


	/**
	 * Create DTO from WCPay response.
	 *
	 * @param array $response Server response.
	 * @return Response
	 */
	public static function create_from_wc_pay_response( $response ) {
		$body    = wp_remote_retrieve_body( $response );
		$code    = wp_remote_retrieve_response_code( $response ) ?? 0;
		$headers = wp_remote_retrieve_headers( $response );
		$message = wp_remote_retrieve_response_message( $response );
		$cookies = wp_remote_retrieve_cookies( $response );
		return new self( $body, $code, $headers, $message, $cookies );
	}



}
