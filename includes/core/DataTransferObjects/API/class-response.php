<?php

namespace WCPay\Core\DataTransferObjects;

use WP_Http_Cookie;

final class Response extends Data_Transfer_Object
{

	/**
	 * @var string $body Response body,
	 */
	private $body;

	/**
	 * @var int $code Status code.
	 */
	private $code;

	/**
	 * @var array $headers Response headers.
	 */
	private $headers;

	/**
	 * @var string $message Response message.
	 */
	private $message;

	/**
	 * @var WP_Http_Cookie[] $cookies Response cookies;
	 */
	private $cookies;

	/**
	 * @var array $responseBody Response parsed to array;
	 */
	private $responseData;

	/**
	 * @param string $body Response body.
	 * @param int $code Response code.
	 * @param array $headers Response headers.
	 * @param string $message Response message.
	 * @param WP_Http_Cookie[] $cookies Response cookies.
	 */
	public function __construct( $body, $code, $headers, $message, $cookies)
	{
		$this->body = $body;
		$this->code = $code;
		$this->headers = $headers;
		$this->message = $message;
		$this->cookies = $cookies;
		$this->responseData = json_decode($body, true);
	}

	/**
	 * Returns true if response is JSON.
	 * @return bool
	 */
	public function is_json_response() {
		return 'application/json' ===  substr ($this->headers['content-type'] ?? '', 0, strlen( 'application/json' ) );
	}

	/**
	 * Get body.
	 *
	 * @return string
	 */
	public function get_body()
	{
		return $this->body;
	}

	/**
	 * Get statzs code.
	 *
	 * @return int
	 */
	public function get_code()
	{
		return $this->code;
	}

	/**
	 * Get headers.
	 *
	 * @return array
	 */
	public function get_headers()
	{
		return $this->headers;
	}

	/**
	 * Get message.
	 *
	 * @return string
	 */
	public function get_message()
	{
		return $this->message;
	}

	/**
	 * Get cookies
	 *
	 * @return WP_Http_Cookie[]
	 */
	public function get_cookies()
	{
		return $this->cookies;
	}

	/**
	 * Get response body as array.
	 * @return array|mixed
	 */
	public function get_response_data()
	{
		$data = $this->responseData;
		// Make sure empty metadata serialized on the client as an empty object {} rather than array [].
		if ( isset( $data['metadata'] ) && empty( $data['metadata'] ) ) {
			$data['metadata'] = new \stdClass();
		}
		return $data;
	}

	/**
	 * Check if response is error one.
	 * @return bool
	 */
	public function is_error_response() {
		return 400 <= $this->code;
	}


	public function get_response_message() {
		return $this->responseData['message'] ?? null;
	}

	public function get_code_from_response_data() {
		return $this->responseData['code'] ?? null;
	}

	public function get_value_from_response_by_key($parameter, $key = 'data') {
		return $this->responseData[$key][$parameter] ?? null;
	}

	public function get_error_code() {
		return $this->responseData['error']['code'] ?? null;
	}

	public function get_error_type() {
		return $this->responseData['error']['type'] ?? null;
	}

	public function get_error_message() {
		return $this->responseData['error']['message'] ?? null;
	}

	public function has_code_in_response_data() {
		return isset( $this->responseData['code'] );
	}

	public function has_error_in_responsedata() {
		return isset ($this->responseData['error']);
	}

	public function has_amount_too_small_error_code() {
		return $this->has_code_in_response_data() && 'amount_too_small' === $this->responseData['code'] ;
	}


	/**
	 * @param array $response Server response.
	 * @return Response
	 */

	public static function create_from_wc_pay_response($response) {
		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response ) ?? 0;
		$headers = wp_remote_retrieve_headers($response);
		$message = wp_remote_retrieve_response_message($response);
		$cookies = wp_remote_retrieve_cookies($response);
		return new self($body, $code, $headers, $message, $cookies);
	}



}
