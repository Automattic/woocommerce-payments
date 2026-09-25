<?php
/**
 * Tests for retired activation notices.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Legacy classes cannot reinstall the retired experiment.
 */
class Retired_Kyc_Notices_Test extends WCPAY_UnitTestCase {

	/** @dataProvider retired_notices_provider */
	public function test_legacy_init_does_not_register_handlers( string $class_name ): void {
		$notice = new $class_name(
			$this->createMock( WC_Payment_Gateway_WCPay::class ),
			$this->createMock( WC_Payments_Account::class )
		);
		$notice->init_hooks();
		$notice->init_global_hooks();

		$this->assertFalse( has_action( 'admin_init', [ $notice, 'handle_cta' ] ) );
		$this->assertFalse( has_action( 'admin_enqueue_scripts', [ $notice, 'enqueue_script' ] ) );
		$this->assertFalse( $notice->should_show() );
	}

	public function retired_notices_provider(): array {
		return [
			[ 'WC_Payments_Test_To_Live_Notice' ],
			[ 'WC_Payments_Post_Kyc_Activation_Notice' ],
		];
	}
}
