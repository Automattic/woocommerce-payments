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
		if ( true ) {
			echo "===== GENERIC REQUEST =====\n";
			$request = new WCPay\Core\Server\Request\Generic( WC_Payments_API_Client::PAYMENT_METHODS_API, 'POST' );
			$request->use_user_token();
			$this->dump_request( $request );
		}

		if ( true ) {
			echo "===== CREATE INTENT REQUEST =====\n";
			$request = new Request\Create_Intent();
			$request->set_amount( 100 );
			$this->dump_request( $request );
		}

		if ( true ) {
			// Make sure extending the request does not work outside of `apply_filters`.
			echo "===== EXTEND WITHOUT FILTERS =====\n";
			$request = new Request\Create_Intent();
			$request->set_amount( 100 );
			try {
				Request\WooPay_Create_Intent::extend( $request );
			} catch ( Exception $e ) {
				echo 'Exception message: ' . $e->getMessage() . "\n\n";
			}
		}

		if ( true ) {
			echo "===== EXTEND TEMPLATE =====\n";

			$callback = function ( Request\Create_Intent $base_request ): Request\WooPay_Create_Intent {
				$request = Request\WooPay_Create_Intent::extend( $base_request );
				$request->set_save_payment_method_to_platform( true );
				return $request;
			};

			add_filter( 'wcpay_create_intent_request', $callback );

			$request = new Request\Create_Intent();
			$request->set_amount( 100 );
			$request = $request->apply_filters( 'wcpay_create_intent_request' );

			remove_filter( 'wcpay_create_intent_request', $callback );

			$this->dump_request( $request );
		}

		if ( true ) {
			echo "===== UPDATING VALUES =====\n";
			// TBD
			// add_filter( 'wcpay_create_intent_request', [ $this, 'value_update' ], 10, 2 );
			// function value_update( Request\Create_Intent $base_request, int $replacement_amount ): Request\Create_Intent {
			// 	$base_request->set_amount( $replacement_amount );
			// 	return $base_request;
			// }
		}

		exit;
	}


	function dump_request( $request ) {
		var_dump(
			[
				'object'   => $request,
				'response' => WC_Payments::get_payments_api_client()->send_request( $request ),
				'getters'  => [
					'params'              => $request->get_params(),
					'api'                 => $request->get_api(),
					'method'              => $request->get_method(),
					'site_specific'       => $request->is_site_specific(),
					'use_user_token'      => $request->should_use_user_token(),
					'return_raw_response' => $request->should_return_raw_response(),
				],
			]
		);
		echo "\n\n";
	}
}

class Rados_HTTP_Client extends WC_Payments_Http {
	public function remote_request( $args, $body = null, $is_site_specific = true, $use_user_token = false ) {
		$body = [
			'id' => 'obj_XYZ',
		];

		return [
			'code' => 200,
			'body' => json_encode( $body )
		];
	}
}

new Temp_Request_Examples();

// phpcs:enable
