<?php
/**
 * Class WooPay_Scheduler_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\WooPay_Scheduler;

/**
 * WooPay_Scheduler_Test unit tests.
 */
class WooPay_Scheduler_Test extends WP_UnitTestCase {

	/**
	 * WooPay_Scheduler instance.
	 * @var WooPay_Scheduler
	 */
	private $scheduler;

	public function set_up() {
		parent::set_up();

		$this->scheduler = new WooPay_Scheduler();
	}

	public function test_get_incompatible_extensions() {

		$incompatible_extensions = [
			'test-extension',
			'test-extension-2',
		];

		$this->mock_api( $incompatible_extensions );

		$returned_incompatible_extensions = $this->scheduler->get_incompatible_extensions();

		$this->assertEquals( $incompatible_extensions, $returned_incompatible_extensions );
	}

	public function test_disable_woopay_when_incompatible_extension_active() {

		$incompatible_extensions = [
			'test-extension',
			'test-extension-2',
		];

		$this->mock_api( $incompatible_extensions );

		$pre_http_request = function () {
			return [ 'test-extension/test-extension.php' ];
		};

		add_filter( 'pre_option_active_plugins', $pre_http_request, 10, 3 );

		delete_option( 'woopay_disabled_invalid_extensions' );

		$this->scheduler->disable_woopay_if_incompatible_extension_active();

		$this->assertTrue( get_option( 'woopay_disabled_invalid_extensions', null ) );
	}

	public function test_disable_woopay_when_no_incompatible_extension_active() {

		$incompatible_extensions = [
			'test-extension',
			'test-extension-2',
		];

		$this->mock_api( $incompatible_extensions );

		$pre_http_request = function () {
			return [ 'test-extension/test-extension-3.php' ];
		};

		update_option( 'woopay_disabled_invalid_extensions', true );

		add_filter( 'pre_option_active_plugins', $pre_http_request, 10, 3 );

		$this->scheduler->disable_woopay_if_incompatible_extension_active();

		$this->assertNull( get_option( 'woopay_disabled_invalid_extensions', null ) );
	}

	private function mock_api( $incompatible_extensions ) {
		// Mocks the remote server response.
		$pre_http_request = function ( $preempt, $parsed_args, $url ) use ( $incompatible_extensions ) {
			return [ 'body' => wp_json_encode( [ 'incompatible_extensions' => $incompatible_extensions ] ) ];
		};

		add_filter( 'pre_http_request', $pre_http_request, 10, 3 );

		// Mocks the jetpack token.
		$jetpack_token = function ( $value, $name ) {
			if ( 'blog_token' === $name ) {
				return 'moked.token';
			}

			return $value;
		};

		add_filter( 'jetpack_options', $jetpack_token, 10, 2 );
	}
}
