<?php
namespace WCPay\Core\API;

use WC_Payments;
use WC_Payments_Http;
use WC_Payments_Utils;
use WCPay\Core\Contracts\API\Request\Base_Request;
use WCPay\Core\DataTransferObjects\API\Request\Create_Charge;;

use WCPay\Core\DataTransferObjects\API\Request\Create_Intention;
use WCPay\Core\DataTransferObjects\API\Request\Request;
use WCPay\Core\DataTransferObjects\Response;
use WCPay\Core\Enums\Http_Methods;
use WCPay\Core\Enums\Wc_Pay_Endpoints;
use WCPay\Core\Factories\Create_Charge_Dto;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Exceptions\API_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Logger;

class Wc_Pay_Request
{

	/**
	 * API timeout in seconds.
	 */
	const API_TIMEOUT_SECONDS = 70;

	/**
	 * Common keys in API requests/responses that we might want to redact.
	 */
	const API_KEYS_TO_REDACT = [
		'client_secret',
		'email',
		'name',
		'phone',
		'line1',
		'line2',
		'postal_code',
		'state',
		'city',
		'country',
		'customer_name',
		'customer_email',
	];

	/**
	 * An HTTP client implementation used to send HTTP requests.
	 *
	 * @var WC_Payments_Http $$http_client Used to send HTTP requests.
	 */
	private $http_client;

	/**
	 * User agent.
	 * @var string $user_agent
	 */
	private $user_agent;

	/**
	 * @param string           $user_agent  - User agent string to report in requests.
	 * @param WC_Payments_Http $http_client - Used to send HTTP requests.
	 */
	public function __construct( $http_client, $user_agent ) {
		$this->http_client = $http_client;
		$this->user_agent = $user_agent;
	}

	/**
	 * Create charge endpoint.
	 *
	 * @param Create_Charge $charge
	 * @return \WCPay\Core\DataTransferObjects\API\Charge
	 */
	public function create_charge(Create_Charge $charge) {
		$response = $this->request(new Request($charge->to_wcpay_request(), Http_Methods::POST, Wc_Pay_Endpoints::CHARGES_API));
		return Create_Charge_Dto::create_from_wc_pay_response( $response );
	}

	public function create_intention(Create_Intention $intention){
		$response = $this->request(new Request($intention->to_wcpay_request(), Http_Methods::POST, Wc_Pay_Endpoints::INTENTIONS_API));
	}

	public function request (Base_Request $request) {
		$response = Response::create_from_wc_pay_response( $this->send_request($request));
		$this->check_response_for_errors($response);
		return $response;

	}

	public function raw_request (Base_Request $request) {
		$response =  $this->send_request($request);
		$this->check_response_for_errors(Response::create_from_wc_pay_response($response));
		return $response;
	}

	/**
	 * Private function to request to remote client.
	 *
	 * @param Base_Request $request Request object.
	 * @return array
	 * @throws API_Exception
	 * @throws Amount_Too_Small_Exception
	 */
	private function send_request(Base_Request $request) {
		$params = $request->get_parameters();
		$method = $request->get_method();
		$route = $request->get_route();
		$params = wp_parse_args(
			$params,
			[
				'test_mode' => WC_Payments::get_gateway()->is_in_test_mode(),
			]
		);
		if ( ! isset( $params['level3']['line_items'] ) || ! is_array( $params['level3']['line_items'] ) || 0 === count( $params['level3']['line_items'] ) ) {
			$params['level3']['line_items'] = [
				[
					'discount_amount'     => 0,
					'product_code'        => 'empty-order',
					'product_description' => 'The order is empty',
					'quantity'            => 1,
					'tax_amount'          => 0,
					'unit_cost'           => 0,
				],
			];
		}
		$params = apply_filters( 'wcpay_api_request_params', $params, $route, $method );
		$url = Wc_Pay_Endpoints::ENDPOINT_BASE;
		if ( $request->is_site_specific() ) {
			$url .= '/' . Wc_Pay_Endpoints::ENDPOINT_SITE_FRAGMENT;
		}
		$url .= '/' . Wc_Pay_Endpoints::ENDPOINT_REST_BASE . '/' . $route;
		$headers = $request->get_headers();
		$headers['Content-Type'] = 'application/json; charset=utf-8';
		$headers['User-Agent']   = $this->user_agent;
		$body                    = null;

		$redacted_params = WC_Payments_Utils::redact_array( $params, self::API_KEYS_TO_REDACT );
		$redacted_url    = $url;

		if ( in_array( $method, [ Http_Methods::GET, Http_Methods::DELETE ], true ) ) {
			$url          .= '?' . http_build_query( $params );
			$redacted_url .= '?' . http_build_query( $redacted_params );
		} else {
			$headers['Idempotency-Key'] = $this->uuid();
			$body                       = wp_json_encode( $params );
			if ( ! $body ) {
				throw new API_Exception(
					__( 'Unable to encode body for request to WooCommerce Payments API.', 'woocommerce-payments' ),
					'wcpay_client_unable_to_encode_json',
					0
				);
			}
		}

		$env                    = [];
		$env['WP_User']         = is_user_logged_in() ? wp_get_current_user()->user_login : 'Guest (non logged-in user)';
		$env['HTTP_REFERER']    = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '--' ) );
		$env['HTTP_USER_AGENT'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '--' ) );
		$env['REQUEST_URI']     = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '--' ) );
		$env['DOING_AJAX']      = defined( 'DOING_AJAX' ) && DOING_AJAX;
		$env['DOING_CRON']      = defined( 'DOING_CRON' ) && DOING_CRON;
		$env['WP_CLI']          = defined( 'WP_CLI' ) && WP_CLI;
		Logger::log(
			'ENVIRONMENT: '
			. var_export( $env, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		);

		Logger::log( "REQUEST " .$method ." $redacted_url" );
		Logger::log(
			'HEADERS: '
			. var_export( $headers, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		);

		if ( null !== $body ) {
			Logger::log(
				'BODY: '
				. var_export( $redacted_params, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			);
		}

		return $this->http_client->remote_request(
			[
				'url'             => $url,
				'method'          => $method,
				'headers'         => apply_filters( 'wcpay_api_request_headers', $headers ),
				'timeout'         => self::API_TIMEOUT_SECONDS,
				'connect_timeout' => self::API_TIMEOUT_SECONDS,
			],
			$body,
			$request->is_site_specific(),
			$request->use_user_token()
		);

	}

	private function check_response_for_errors(Response $response) {
		$response_code =$response->get_code();

		$response_body = $response->get_response_data();
		if ( null === $response_body && $response->is_json_response() ) {
			$message = __( 'Unable to decode response from WooCommerce Payments API', 'woocommerce-payments' );
			Logger::error( $message );
			throw new API_Exception(
				$message,
				'wcpay_unparseable_or_null_body',
				$response_code
			);
		}

		// Check error codes for 4xx and 5xx responses.
		if ($response->is_error_response() ) {
			$error_type = null;
			if ( $response->has_amount_too_small_error_code() ) {
				throw new Amount_Too_Small_Exception(
					$response->get_message(),
					$response->get_value_from_response_by_key('minimum_amount'),
					$response->get_value_from_response_by_key('currency'),
					$response_code
				);
			} elseif ( $response->has_error_in_responsedata() ) {
				$this->maybe_act_on_fraud_prevention( $response->get_value_from_response_by_key('decline_code', 'error') ?? '' );

				$error_code    = $response->get_error_code() ?? $response->get_error_type() ?? null;
				$error_message = $response->get_error_message();
				$error_type    = $response->get_error_type();
			} elseif ( $response->has_code_in_response_data() ) {
				$this->maybe_act_on_fraud_prevention( $response->get_code_from_response_data() );

				$error_code    = $response->get_error_code();
				$error_message = $response->get_message();
			} else {
				$error_code    = 'wcpay_client_error_code_missing';
				$error_message = __( 'Server error. Please try again.', 'woocommerce-payments' );
			}

			$message = sprintf(
			// translators: This is an error API response.
				_x( 'Error: %1$s', 'API error message to throw as Exception', 'woocommerce-payments' ),
				$error_message
			);

			Logger::error( "$error_message ($error_code)" );
			throw new API_Exception( $message, $error_code, $response_code, $error_type );
		}
	}

	/**
	 * Returns a v4 UUID.
	 *
	 * @return string
	 */
	private function uuid() {
		$arr    = array_values( unpack( 'N1a/n4b/N1c', random_bytes( 16 ) ) );
		$arr[2] = ( $arr[2] & 0x0fff ) | 0x4000;
		$arr[3] = ( $arr[3] & 0x3fff ) | 0x8000;
		return vsprintf( '%08x-%04x-%04x-%04x-%04x%08x', $arr );
	}

	/**
	 * If error code indicates fraudulent activity, trigger fraud prevention measures.
	 *
	 * @param string $error_code Error code.
	 *
	 * @return void
	 */
	private function maybe_act_on_fraud_prevention( string $error_code ) {
		// Might be flagged by Stripe Radar or WCPay card testing prevention services.
		$is_fraudulent = 'fraudulent' === $error_code || 'wcpay_card_testing_prevention' === $error_code;
		if ( $is_fraudulent ) {
			$fraud_prevention_service = Fraud_Prevention_Service::get_instance();
			if ( $fraud_prevention_service->is_enabled() ) {
				$fraud_prevention_service->regenerate_token();
				// Here we tried triggering checkout refresh, but it clashes with AJAX handling.
			}
		}
	}


}
