<?php
/**
 * Class WP_UnitTestCase
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request;
use WCPay\Core\Server\Response;

/**
 * This stub assists IDE in recognizing PHPUnit tests.
 *
 * Class WP_UnitTestCase
 */
class WCPAY_UnitTestCase extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();

		// Use a priority of 9 to ensure that these filters will allow tests that want to mock external requests
		// to hook in with the regular 10 priority and do their thing.
		// But by default we will intercept all external requests.
		add_filter( 'pre_http_request', [ $this, 'filter_intercept_external_requests' ], 9, 3 );
		add_filter( 'woocommerce_get_geolocation', [ $this, 'filter_mock_wc_geolocation' ], 9, 2 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'filter_intercept_external_requests' ], 9, 3 );
		remove_filter( 'woocommerce_get_geolocation', [ $this, 'filter_mock_wc_geolocation' ], 9, 2 );

		parent::tear_down();
	}

	/**
	 * Intercept external requests and return a service unavailable response.
	 *
	 * This way we don't allow relying on external services for tests (fragile and slow tests) and force those tests
	 * that care about a response to mock the request response.
	 *
	 * @see WP_Http::request()
	 *
	 * @param false|array|WP_Error $response    A preemptive return value of an HTTP request. Default false.
	 * @param array                $parsed_args HTTP request arguments.
	 * @param string               $url         The request URL.
	 *
	 * @return array
	 */
	public function filter_intercept_external_requests( $response, $parsed_args, $url ) {
		// Return a service unavailable response.
		return [
			'body'          => '',
			'response'      => [
				'code' => WP_Http::SERVICE_UNAVAILABLE,
			],
			'headers'       => [],
			'cookies'       => [],
			'http_response' => null,
		];
	}

	/**
	 * Intercept geolocation requests and return mock data.
	 *
	 * @param array $geolocation
	 * @param string $ip_address
	 *
	 * @return array
	 */
	public function filter_mock_wc_geolocation( $geolocation, $ip_address ) {
		$ip_geolocation_test_data = json_decode( file_get_contents( __DIR__ . '/unit/test-data/ip-geolocation.json' ), true );

		if ( ! empty( $ip_geolocation_test_data[ $ip_address ] ) ) {
			$geolocation = array_merge( $geolocation, $ip_geolocation_test_data[ $ip_address ] );
		}

		return $geolocation;
	}

	protected function is_wpcom() {
		return defined( 'IS_WPCOM' ) && IS_WPCOM;
	}

	/**
	 * Creates a mock object.
	 *
	 * This method does not work differently from `createMock`,
	 * but the DocBlock comment indicates a proper return type,
	 * combining `MockObject` and the provided class name.
	 *
	 * @template ID
	 * @param class-string<ID> $original_class_name Name of the class to mock.
	 * @return ID|MockObject
	 */
	public function createMock( string $original_class_name ): MockObject { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
		return parent::createMock( $original_class_name );
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
	 * @return Request|MockObject                                   The mocked request.
	 */
	protected function mock_wcpay_request( string $request_class, int $total_api_calls = 1, $request_class_constructor_id = null, $response = null, $api_client_mock = null, $http_mock = null ) {
		$http_mock       = $http_mock ? $http_mock : $this->createMock( WC_Payments_Http::class );
		$api_client_mock = $api_client_mock ? $api_client_mock : $this->createMock( WC_Payments_API_Client::class );

		if ( 1 > $total_api_calls ) {
			$api_client_mock->expects( $this->never() )->method( 'send_request' );

			// No expectation for calls, return here.
			return;
		}
		// Since setMethodsExcept is deprecated, this is the only alternative I came upon.
		$methods_to_mock = array_diff( get_class_methods( $request_class ), [ 'set_hook_args', 'assign_hook' ] );

		$request = $this->getMockBuilder( $request_class )
			->setConstructorArgs( [ $api_client_mock, $http_mock, $request_class_constructor_id ] )
			->onlyMethods( $methods_to_mock )  // Mock all methods except set_hook_args and assign_hook to accommodate filter args when apply_filters is called.
			->getMock();

		$api_client_mock->expects( $this->exactly( $total_api_calls ) )
			->method( 'send_request' )
			->with(
				$this->callback(
					// With filters there is a chance that mock will be changed. With this code we are sure that it belongs to same class.
					function ( $argument ) use ( $request_class, $request ) {
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
		$fn = function ( $existing_request, $class_name ) use ( &$fn, $request ) {
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
