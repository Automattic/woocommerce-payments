<?php
/**
 * Class file for WCPay\Core\Server\Request.
 *
 * @package WooCommerce Payments
 */

// phpcs:disable
use WCPay\Core\Server\Request;

class Temp_Request_Examples {
	public function __construct() {
		add_action( 'template_redirect', [ $this, 'example' ] );
		add_filter( 'wc_payments_http', [ $this, 'mock_http_class' ] );
	}

	public function mock_http_class() {
		return new Rados_HTTP_Client( new Automattic\Jetpack\Connection\Manager( 'woocommerce-payments' ) );
	}

	public function example() {
		add_filter( 'wcpay_create_intent_request', [ $this, 'extention_example' ], 10, 2 );
		add_filter( 'wcpay_create_intent_request', [ $this, 'value_update' ], 10, 2 );


		// $request = new WCPay\Core\Server\Request\Generic( WC_Payments_API_Client::PAYMENT_METHODS_API, 'GET' );
		// $request->use_user_token();

		$request = new Request\Create_Intent();
		$request->set_amount( 100 );
		$suggested_amount = 200; // Something that might come from context, and extensions might use.
		$request = $request->apply_filters( 'wcpay_create_intent_request', $suggested_amount );

		var_dump(
			[
				$request,
				'params'              => $request->get_params(),
				'api'                 => $request->get_api(),
				'method'              => $request->get_method(),
				'site_specific'       => $request->is_site_specific(),
				'use_user_token'      => $request->should_use_user_token(),
				'return_raw_response' => $request->should_return_raw_response(),
				WC_Payments::get_payments_api_client()->send_request( $request ),
			]
		);

		/////
		exit;
	}

	/**
	 * Example how the request can be extended and values updated.
	 */
	function extention_example( Request\Create_Intent $base_request, int $replacement_amount ): Request\WooPay_Create_Intent {
		$request = Request\WooPay_Create_Intent::extend( $base_request );
		$request->set_amount( $replacement_amount );
		$request->set_save_payment_method_to_platform( true );
		return $request;
	}

	/**
	 * Example how some properties can be updated.
	 */
	function value_update( Request\Create_Intent $base_request, int $replacement_amount ): Request\Create_Intent {
		$base_request->set_amount( $replacement_amount );
		return $base_request;
	}
}

class Rados_HTTP_Client extends WC_Payments_Http {
	public function remote_request( $args, $body = null, $is_site_specific = true, $use_user_token = false ) {
		return [
			'code' => 200,
			'body' => json_encode( [
				'id' => 'obj_XYZ',
			] )
		];
	}
}

new Temp_Request_Examples();

// phpcs:enable
