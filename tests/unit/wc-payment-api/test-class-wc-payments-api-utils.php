<?php
/**
 * Class WC_Payments_API_Utils_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_API_Utils unit tests.
 */
class WC_Payments_API_Utils_Test extends WCPAY_UnitTestCase {
	/**
	 * Unit under test
	 *
	 * @var WC_Payments_Api_Utils
	 */
	private $payments_api_utils;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->payments_api_utils = new WC_Payments_Api_Utils();
	}

	public function test_redirect_needs_a_retry() {
		$this->assertFalse( $this->payments_api_utils->is_response_code_present_and_does_not_need_a_retry( 302 ) );
	}

	public function test_network_error_needs_a_retry() {
		$this->assertFalse( $this->payments_api_utils->is_response_code_present_and_does_not_need_a_retry( 0 ) );
	}

	public function test_not_found_needs_no_retry() {
		$this->assertTrue( $this->payments_api_utils->is_response_code_present_and_does_not_need_a_retry( 404 ) );
	}

	public function test_doing_wp_cron_query_parameter_present_in_location_header() {
		$this->assertTrue( $this->payments_api_utils->is_doing_wp_cron_query_parameter_present( $this->get_response( true ) ) );
	}

	public function test_doing_wp_cron_query_parameter_not_present_in_location_header() {
		$this->assertFalse( $this->payments_api_utils->is_doing_wp_cron_query_parameter_present( $this->get_response( false ) ) );
	}

	private function get_response( $doing_wp_cron ) {
		$url                     = '/wp-json/wpcom/v2/sites/test_site/wcpay/payment_methods?test_mode=1&customer=test_customer&type=card&limit=100';
		$doing_wp_cron_parameter = '&doing_wp_cron=1672335550.5365691184997558593750';

		$url = $doing_wp_cron ? $url . $doing_wp_cron_parameter : $url;

		return [
			'headers'  => new Requests_Utility_CaseInsensitiveDictionary(
				[
					'location' => $url,
				]
			),
			'body'     => wp_json_encode( [ 'url' => false ] ),
			'response' => [
				'code'    => 302,
				'message' => 'Found',
			],
		];
	}
}
