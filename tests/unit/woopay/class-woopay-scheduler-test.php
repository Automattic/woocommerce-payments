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
	 * Mocked WC_Payments_API_Client.
	 * @var WC_Payments_API_Client
	 */
	private $mock_api;

	/**
	 * WooPay_Scheduler instance.
	 * @var WooPay_Scheduler
	 */
	private $scheduler;

	public function set_up() {
		parent::set_up();

		$this->mock_api = $this->createMock( WC_Payments_API_Client::class );

		$this->scheduler = new WooPay_Scheduler( $this->mock_api );
	}

	/**
	 * Checks if the warning will show up when and incompatible extension is active.
	 */
	public function test_disable_woopay_when_incompatible_extension_active() {

		$incompatible_extensions = [
			'test-extension',
			'test-extension-2',
		];

		$this->mock_api_response( $incompatible_extensions );

		$active_plugins_mock = function () {
			return [ 'test-extension/test-extension.php' ];
		};

		add_filter( 'pre_option_active_plugins', $active_plugins_mock, 10, 3 );

		delete_option( WooPay_Scheduler::INVALID_EXTENSIONS_FOUND_OPTION_NAME );

		$this->scheduler->update_incompatible_extensions_list_and_maybe_show_warning();

		$this->assertTrue( get_option( WooPay_Scheduler::INVALID_EXTENSIONS_FOUND_OPTION_NAME, null ) );
	}

	/**
	 * Checks if the warning will not show up when and no incompatible extension are active.
	 */
	public function test_disable_woopay_when_no_incompatible_extension_active() {

		$incompatible_extensions = [
			'test-extension',
			'test-extension-2',
		];

		$this->mock_api_response( $incompatible_extensions );

		$active_plugins_mock = function () {
			return [ 'test-extension/test-extension-3.php' ];
		};

		update_option( WooPay_Scheduler::INVALID_EXTENSIONS_FOUND_OPTION_NAME, true );

		add_filter( 'pre_option_active_plugins', $active_plugins_mock, 10, 3 );

		$this->scheduler->update_incompatible_extensions_list_and_maybe_show_warning();

		$this->assertNull( get_option( WooPay_Scheduler::INVALID_EXTENSIONS_FOUND_OPTION_NAME, null ) );
	}

	/**
	 * Checks if the warning will show up after activating an incompatible extension.
	 */
	public function test_show_warning_when_incompatible_extension_is_enabled() {
		update_option( WooPay_Scheduler::INCOMPATIBLE_EXTENSIONS_LIST_OPTION_NAME, [ 'test-extension' ] );
		delete_option( WooPay_Scheduler::INVALID_EXTENSIONS_FOUND_OPTION_NAME );

		$this->scheduler->show_warning_when_incompatible_extension_is_enabled( 'test-extension/test-extension.php' );

		$this->assertTrue( get_option( WooPay_Scheduler::INVALID_EXTENSIONS_FOUND_OPTION_NAME, null ) );
	}

	/**
	 * Checks if the warning will not show up after activating a compatible extension.
	 */
	public function test_will_not_show_warning_when_compatible_extension_is_enabled() {
		update_option( WooPay_Scheduler::INCOMPATIBLE_EXTENSIONS_LIST_OPTION_NAME, [ 'test-extension' ] );
		delete_option( WooPay_Scheduler::INVALID_EXTENSIONS_FOUND_OPTION_NAME );

		$this->scheduler->show_warning_when_incompatible_extension_is_enabled( 'test-extension/test-extension-2.php' );

		$this->assertNull( get_option( WooPay_Scheduler::INVALID_EXTENSIONS_FOUND_OPTION_NAME, null ) );
	}

	/**
	 * Checks if the warning will not show up after deactivating the last incompatible extension.
	 */
	public function test_will_stop_showing_warning_when_incompatible_extension_is_removed() {
		update_option( WooPay_Scheduler::INCOMPATIBLE_EXTENSIONS_LIST_OPTION_NAME, [ 'test-extension' ] );

		$active_plugins_mock = function () {
			return [
				'test-extension/test-extension-3.php',
				'test-extension/test-extension.php',
			];
		};
		add_filter( 'pre_option_active_plugins', $active_plugins_mock, 10, 3 );

		update_option( WooPay_Scheduler::INVALID_EXTENSIONS_FOUND_OPTION_NAME, true );

		$this->scheduler->hide_warning_when_incompatible_extension_is_disabled( 'test-extension/test-extension.php' );

		$this->assertNull( get_option( WooPay_Scheduler::INVALID_EXTENSIONS_FOUND_OPTION_NAME, null ) );
	}

	/**
	 * Checks if the warning will still show up after deactivating only one incompatible extension.
	 */
	public function test_will_keep_showing_warning_when_only_one_incompatible_extension_is_removed() {
		update_option(
			WooPay_Scheduler::INCOMPATIBLE_EXTENSIONS_LIST_OPTION_NAME,
			[
				'test-extension',
				'test-extension-3',
			]
		);

		$active_plugins_mock = function () {
			return [
				'test-extension/test-extension-3.php',
				'test-extension/test-extension.php',
			];
		};
		add_filter( 'pre_option_active_plugins', $active_plugins_mock, 10, 3 );

		update_option( WooPay_Scheduler::INVALID_EXTENSIONS_FOUND_OPTION_NAME, true );

		$this->scheduler->hide_warning_when_incompatible_extension_is_disabled( 'test-extension/test-extension.php' );

		$this->assertTrue( get_option( WooPay_Scheduler::INVALID_EXTENSIONS_FOUND_OPTION_NAME, null ) );
	}

	/**
	 * Will check if the incompatible extension is found.
	 */
	public function test_contains_invalid_extension() {
		$active_plugins_mock = [
			'test-extension/test-extension-3.php',
			'test-extension/test-extension.php',
		];

		$incompatible_extensions = [
			'test-extension-3',
			'test-extension-2',
		];

		$found = $this->scheduler->contains_incompatible_extension( $active_plugins_mock, $incompatible_extensions );

		$this->assertTrue( $found );
	}

	/**
	 * Will check if no incompatible extension is found.
	 */
	public function test_does_not_contains_invalid_extension() {
		$active_plugins_mock = [
			'test-extension/test-extension-3.php',
			'test-extension/test-extension.php',
		];

		$incompatible_extensions = [ 'test-extension-2' ];

		$found = $this->scheduler->contains_incompatible_extension( $active_plugins_mock, $incompatible_extensions );
		$this->assertFalse( $found );
	}

	/**
	 * Mocks the return of WC_Payments_API_Client::get_woopay_incompatible_extensions.
	 *
	 * @param array $incompatible_extensions
	 */
	private function mock_api_response( $incompatible_extensions ) {
		$this->mock_api->method( 'get_woopay_incompatible_extensions' )->willReturn( [ 'incompatible_extensions' => $incompatible_extensions ] );
	}
}
