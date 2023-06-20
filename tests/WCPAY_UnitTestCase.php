<?php
/**
 * Class WP_UnitTestCase
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Server\Request;
use WCPay\Core\Server\Response;

/**
 * This stub assists IDE in recognizing PHPUnit tests.
 *
 * Class WP_UnitTestCase
 */
class WCPAY_UnitTestCase extends WP_UnitTestCase {
	protected function is_wpcom() {
		return defined( 'IS_WPCOM' ) && IS_WPCOM;
	}

	/**
	 * Mocks an outgoing WCPay request (Those from WCPay\Core\Server\Request).
	 *
	 * Creates a new mock of the particular request class, and uses the `wcpay_create_request`
	 * filter to plug it in, just once, and whenver this particular class of request is created.
	 *
	 * The API client and HTTP mocks can be optionally provided, if they need manipulation as well.
	 *
	 * If the given request class does not have a specific `format_response` method, you can provide
	 * the expexted response here. If there is a `format_response` method, mock it manually.
	 *
	 * @param  string                 $request_class                The class of the mocked request.
	 * @param  int                    $total_api_calls              Number of same api calls that will be executed. Used when you want to send multiple request, using the same instance of class, i.e. retry mechanism.
	 * @param  string|null            $request_class_constructor_id Used when constructor class gets ID (like intent id or charge id) and passes it as a constructor dependency in mocked request class.
	 * @param  mixed                  $response                     The expected response.
	 * @param  WC_Payments_API_Client $api_client_mock              Specific API client mock if necessary.
	 * @param  WC_Payments_Http       $http_mock                    Specific HTTP mock if necessary.
	 *
	 * @return Request                                                      The mocked request.
	 */
	protected function mock_wcpay_request( string $request_class, int $total_api_calls = 1, $request_class_constructor_id = null, $response = null, $api_client_mock = null, $http_mock = null ) {
		$http_mock       = $http_mock ? $http_mock : $this->createMock( WC_Payments_Http::class );
		$api_client_mock = $api_client_mock ? $api_client_mock : $this->createMock( WC_Payments_API_Client::class );

		if ( 1 > $total_api_calls ) {
			$api_client_mock->expects( $this->never() )->method( 'send_request' );

			// No expectation for calls, return here.
			return;
		}

		$request = $this->getMockBuilder( $request_class )
			->setConstructorArgs( [ $api_client_mock, $http_mock, $request_class_constructor_id ] )
			->getMock();

		$api_client_mock->expects( $this->exactly( $total_api_calls ) )
			->method( 'send_request' )
			->with(
				$this->callback(
					// With filters there is a chance that mock will be changed. With this code we are sure that it belongs to same class.
					function( $argument ) use ( $request_class, $request ) {
						return get_class( $request ) === get_class( $argument ) || is_subclass_of( $argument, $request_class );
					}
				)
			);

		if ( ! is_null( $response ) ) {
			$request
				->expects( $this->exactly( $total_api_calls ) )
				->method( 'format_response' )
				->willReturn( $response instanceof Response ? $response : new Response( $response ) );
		}

		// An anonymous callback, which will be used once and disposed.
		$fn = function( $existing_request, $class_name ) use ( &$fn, $request ) {
			if ( ! is_null( $existing_request ) ) {
				return $existing_request; // Another `mock_wcpay_request` in action.
			}

			if ( ! ( $request instanceof $class_name ) ) {
				return $existing_request; // Another mock.
			}

			// Only do this once.
			remove_filter( 'wcpay_create_request', $fn );
			return $request;
		};

		add_filter( 'wcpay_create_request', $fn, 10, 2 );

		return $request;
	}
}
