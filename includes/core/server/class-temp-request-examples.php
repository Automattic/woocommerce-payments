<?php
/**
 * Class file for WCPay\Core\Server\Request.
 *
 * @package WooCommerce Payments
 */

// phpcs:disable
use WCPay\Core\Exceptions\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;

class Temp_Request_Examples {
	public function __construct() {
		if ( isset( $_GET['show_basic_examples'] ) ) {
			add_action( 'template_redirect', [ $this, 'example' ] );
			add_filter( 'wc_payments_http', [ $this, 'mock_http_class' ] );
		}

		add_filter( 'wcpay_create_and_confirm_intention_request', [ $this, 'woopay_intention_request'], 10, 3 );
	}

	/**
	 * Example what consumers like WooPay should do to extend the request.
	 *
	 * @param Request\Create_And_Confirm_Intention $base_request   The request that's being modified.
	 * @param WC_Order                             $order          The order which needs payment.
	 * @param bool                                 $is_platform_pm A pre-calculated flag.
	 * @return Request\WooPay_Create_And_Confirm_Intention
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function woopay_intention_request( Request\Create_And_Confirm_Intention $base_request, $is_using_saved_payment_method, $used_stripe_on_checkout, $order ) {
		$request = Request\WooPay_Create_And_Confirm_Intention::extend( $base_request );


	}

	public function mock_http_class() {
		return new Rados_HTTP_Client( new Automattic\Jetpack\Connection\Manager( 'woocommerce-payments' ) );
	}

	public function example() {
		echo "<pre>\n";

		if ( false ) {
			echo "===== CREATE INTENT REQUEST =====\n";
			$request = WC_Payments::create_request( Request\Create_Intent::class );
			$request->set_amount( 100 );
			$request->set_currency( 'eur' );
			$this->dump_request( $request );
		}

		if ( false ) {
			// Make sure extending the request does not work outside of `apply_filters`.
			echo "===== EXTEND WITHOUT FILTERS =====\n";
			$request = WC_Payments::create_request( Request\Create_Intent::class );
			$request->set_amount( 100 );
			try {
				Request\WooPay_Create_Intent::extend( $request );
			} catch ( Exception $e ) {
				echo 'Exception message: ' . $e->getMessage() . "\n\n";$is_platform_payment_method = ! $is_using_saved_payment_method &&
				                                                                                     $used_stripe_on_checkout &&
				                                                                                     // This flag is useful to differentiate between PRB, blocks and shortcode checkout, since this endpoint is being used for all of them.
				                                                                                     ! empty( $_POST['wcpay-is-platform-payment-method'] ) && // phpcs:ignore WordPress.Security.NonceVerification
				                                                                                     filter_var( $_POST['wcpay-is-platform-payment-method'], FILTER_VALIDATE_BOOLEAN );
				// This meta is only set by WooPay.
				// We want to handle the intention creation differently when there are subscriptions.
				// We're using simple products on WooPay so the current logic for WCPay subscriptions won't work there.

				if ( ! $order  ) {
					throw new Invalid_Request_Parameter_Exception(
						'Invalid order passed',
						'wcpay_core_invalid_request_parameter_order'
					);
				}
				$request->set_has_woopay_subscription( '1' === $order->get_meta( '_woopay_has_subscription' ) );
				$request->set_is_platform_payment_method( $is_platform_payment_method );

				return $request;
			}
		}

		if ( false ) {
			echo "===== EXTEND TEMPLATE =====\n";

			$callback = function ( Request\Create_Intent $base_request ): Request\WooPay_Create_Intent {
				$request = Request\WooPay_Create_Intent::extend( $base_request )
					->set_save_payment_method_to_platform( true );
				return $request;
			};

			add_filter( 'wcpay_create_intent_request', $callback );

			$request = WC_Payments::create_request( Request\Create_Intent::class );
			$request->set_amount( 100 );
			$request->set_currency( 'eur' );
			$request = $request->apply_filters( 'wcpay_create_intent_request' );

			remove_filter( 'wcpay_create_intent_request', $callback );

			$this->dump_request( $request );
		}

		if ( false ) {
			echo "===== UPDATING VALUES =====\n";
			$request = WC_Payments::create_request( Request\Create_Intent::class );
			$request->set_amount( 100 );
			$request->set_currency( 'eur' );

			$callback = function ( Request\Create_Intent $request, string $new_currency ) {
				$request->set_currency( $new_currency );
				return $request;
			};
			add_filter( 'wcpay_create_intent_request', $callback, 10, 2 );

			// This is an example of a variable, which comes from somewhere else, and how it can be passed to filters.
			$other_currency = 'usd';
			$request->apply_filters( 'wcpay_create_intent_request', $other_currency );

			remove_filter( 'wcpay_create_intent_request', $callback );

			$this->dump_request( $request );
		}

		if ( false ) {
			echo "===== PROTECTING IMMUTABLE VALUES =====\n";
			$request = WC_Payments::create_request( Request\Create_Intent::class );
			$request->set_amount( 100 );
			$request->set_currency( 'eur' );

			$callback = function ( Request\Create_Intent $request ) {
				try {
					$request->set_amount( 200 );
				} catch ( Exception $e ) {
					echo 'Exception message: ' . $e->getMessage() . "\n\n";
				}
				return $request;
			};

			add_filter( 'wcpay_create_intent_request', $callback, 10 );
			$request->apply_filters( 'wcpay_create_intent_request' );
			remove_filter( 'wcpay_create_intent_request', $callback );
		}

		if ( false ) {
			echo "===== ENSURING INITIALIZED REQUESTS =====\n";
			$request = WC_Payments::create_request( Request\Create_Intent::class );
			$request->set_amount( 100 );
			try {
				$request->get_params();
			} catch ( Exception $e ) {
				echo 'Exception message: ' . $e->getMessage() . "\n\n";
			}
		}

		if ( false ) {
			echo "===== GENERIC GET REQUEST =====\n";
			// ToDo: Make sure IDs are properly set somehow.
			$request = new Request\Generic( WC_Payments_API_Client::PAYMENT_METHODS_API . '/pm_abc123', REQUESTS::GET );
			$request->use_user_token();
			$this->dump_request( $request );
		}

		if ( false ) {
			echo "===== GENERIC POST REQUEST =====\n";
			$request = new Request\Generic(
				WC_Payments_API_Client::CUSTOMERS_API,
				Requests::POST,
				[
					'first_name' => 'John',
					'last_name'  => 'Doe',
				]
			);
			$request->use_user_token();
			$this->dump_request( $request );
		}

		if ( false ) {
			echo "===== GENERIC POST REQUEST MODIFICATIONS =====\n";
			$request = new Request\Generic(
				WC_Payments_API_Client::CUSTOMERS_API,
				Requests::POST,
				[
					'first_name' => 'John',
					'last_name'  => 'Doe',
				]
			);

			$callback = function( Request\Generic $request ) {
				$request->set( 'age', 42 );
				return $request;
			};

			add_filter( 'wcpay_create_customer_request', $callback );
			$request->apply_filters( 'wcpay_create_customer_request' );
			remove_filter( 'wcpay_create_customer_request', $callback );

			$this->dump_request( $request );
		}

		if ( true ) {
			echo "===== CREATE AND CONFIRM INTENTION REQUEST =====\n";
			$request = WC_Payments::create_request( Request\Create_And_Confirm_Intention::class );
			$request->set_amount( 300 );
			$request->set_currency_code( 'eur' );
			$request->set_metadata( [ 'order_number' => 420 ] );
			$request->set_payment_method( 'pm_XYZ' );
			$request->set_customer( 'cus_ZYX' );
			$request->set_capture_method( true );
			$request->setup_future_usage();
			$request->set_level3( [ 'level3' => 'level3' ] );
			$request->set_off_session();
			$request->set_payment_methods( [ 'card' ] );
			$request->set_cvc_confirmation( 'something_uknown' );

			var_dump( $request->get_params() );
		}

		exit;
	}


	function dump_request( $request ) {
		$response = WC_Payments::get_payments_api_client()->send_request( $request );

		var_dump(
			[
				'object'   => $request,
				'response' => $response['id'],
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
