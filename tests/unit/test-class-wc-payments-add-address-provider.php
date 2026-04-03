<?php
/**
 * Class WC_Payments_Add_Address_Provider_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for WC_Payments::add_address_provider().
 */
class WC_Payments_Add_Address_Provider_Test extends WCPAY_UnitTestCase {

	/**
	 * Mock WC_Payment_Gateway_WCPay.
	 *
	 * @var MockObject|WC_Payment_Gateway_WCPay
	 */
	private $mock_gateway;

	/**
	 * Mock WC_Payments_Account.
	 *
	 * @var MockObject|WC_Payments_Account
	 */
	private $mock_account;

	/**
	 * The original gateway stored before the test swaps it.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $original_gateway;

	/**
	 * The original account stored before the test swaps it.
	 *
	 * @var WC_Payments_Account
	 */
	private $original_account;

	public function set_up() {
		parent::set_up();

		$this->mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->mock_account = $this->createMock( WC_Payments_Account::class );

		$this->original_gateway = WC_Payments::get_gateway();
		$this->original_account = WC_Payments::get_account_service();

		WC_Payments::set_gateway( $this->mock_gateway );
		WC_Payments::set_account_service( $this->mock_account );
	}

	public function tear_down() {
		WC_Payments::set_gateway( $this->original_gateway );
		WC_Payments::set_account_service( $this->original_account );

		parent::tear_down();
	}

	public function test_add_address_provider_returns_providers_unchanged_when_gateway_disabled() {
		$this->mock_gateway->method( 'is_enabled' )->willReturn( false );

		$result = WC_Payments::add_address_provider( [] );

		$this->assertSame( [], $result );
	}

	public function test_add_address_provider_returns_providers_unchanged_when_account_rejected() {
		$this->mock_gateway->method( 'is_enabled' )->willReturn( true );
		$this->mock_account->method( 'is_account_rejected' )->willReturn( true );
		$this->mock_account->method( 'is_account_under_review' )->willReturn( false );

		$result = WC_Payments::add_address_provider( [] );

		$this->assertSame( [], $result );
	}

	public function test_add_address_provider_returns_providers_unchanged_when_account_under_review() {
		$this->mock_gateway->method( 'is_enabled' )->willReturn( true );
		$this->mock_account->method( 'is_account_rejected' )->willReturn( false );
		$this->mock_account->method( 'is_account_under_review' )->willReturn( true );

		$result = WC_Payments::add_address_provider( [] );

		$this->assertSame( [], $result );
	}

	public function test_add_address_provider_adds_provider_when_account_is_active() {
		if ( ! class_exists( 'Automattic\\WooCommerce\\Internal\\AddressProvider\\AbstractAutomatticAddressProvider' ) ) {
			$this->markTestSkipped( 'AbstractAutomatticAddressProvider not available (requires WC 10.3+).' );
		}

		$this->mock_gateway->method( 'is_enabled' )->willReturn( true );
		$this->mock_account->method( 'is_account_rejected' )->willReturn( false );
		$this->mock_account->method( 'is_account_under_review' )->willReturn( false );

		$result = WC_Payments::add_address_provider( [] );

		$this->assertCount( 1, $result );
		$this->assertInstanceOf( WC_Payments_Address_Provider::class, $result[0] );
	}

	public function test_add_address_provider_returns_providers_unchanged_when_wc_class_missing() {
		$this->mock_gateway->method( 'is_enabled' )->willReturn( true );
		$this->mock_account->method( 'is_account_rejected' )->willReturn( false );
		$this->mock_account->method( 'is_account_under_review' )->willReturn( false );

		if ( class_exists( 'Automattic\\WooCommerce\\Internal\\AddressProvider\\AbstractAutomatticAddressProvider' ) ) {
			$this->markTestSkipped( 'AbstractAutomatticAddressProvider is available; cannot test missing-class guard.' );
		}

		$result = WC_Payments::add_address_provider( [] );

		$this->assertSame( [], $result );
	}
}
