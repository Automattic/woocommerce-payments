<?php
/**
 * Class Link_WooPay_Mutual_Exclusion_Cleanup_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace unit\migrations;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Payment_Gateway_WCPay;
use WCPay\Migrations\Link_WooPay_Mutual_Exclusion_Cleanup;
use WCPAY_UnitTestCase;

/**
 * WCPay\Migrations\Link_WooPay_Mutual_Exclusion_Cleanup unit tests.
 */
class Link_WooPay_Mutual_Exclusion_Cleanup_Test extends WCPAY_UnitTestCase {

	const VERSION_BEFORE_MIGRATION = '11.3.0';

	/**
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $card_gateway;

	/**
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $link_gateway;

	/**
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $sepa_gateway;

	/**
	 * @var Link_WooPay_Mutual_Exclusion_Cleanup
	 */
	private $migration;

	public function set_up() {
		parent::set_up();

		$this->card_gateway = $this->create_gateway_mock( 'card' );
		$this->link_gateway = $this->create_gateway_mock( 'link' );
		$this->sepa_gateway = $this->create_gateway_mock( 'sepa_debit' );

		$this->migration = new Link_WooPay_Mutual_Exclusion_Cleanup(
			$this->card_gateway,
			[
				'card'       => $this->card_gateway,
				'link'       => $this->link_gateway,
				'sepa_debit' => $this->sepa_gateway,
			]
		);
	}

	public function tear_down() {
		delete_option( 'woocommerce_woocommerce_payments_version' );

		parent::tear_down();
	}

	public function test_it_turns_link_off_on_every_gateway_when_woopay_is_on() {
		update_option( 'woocommerce_woocommerce_payments_version', self::VERSION_BEFORE_MIGRATION );
		$this->set_up_settings( 'yes', [ 'card', 'link', 'sepa_debit' ] );

		$this->link_gateway->expects( $this->once() )->method( 'disable' );
		$this->card_gateway->expects( $this->never() )->method( 'disable' );
		$this->sepa_gateway->expects( $this->never() )->method( 'disable' );
		foreach ( [ $this->card_gateway, $this->link_gateway, $this->sepa_gateway ] as $gateway ) {
			$gateway->expects( $this->once() )
				->method( 'update_option' )
				->with( 'upe_enabled_payment_method_ids', [ 'card', 'sepa_debit' ] );
		}

		$this->migration->maybe_migrate();
	}

	public function test_it_keeps_link_when_woopay_is_off() {
		update_option( 'woocommerce_woocommerce_payments_version', self::VERSION_BEFORE_MIGRATION );
		$this->set_up_settings( 'no', [ 'card', 'link' ] );

		$this->expect_no_changes();

		$this->migration->maybe_migrate();
	}

	public function test_it_does_nothing_when_link_is_off() {
		update_option( 'woocommerce_woocommerce_payments_version', self::VERSION_BEFORE_MIGRATION );
		$this->set_up_settings( 'yes', [ 'card', 'sepa_debit' ] );

		$this->expect_no_changes();

		$this->migration->maybe_migrate();
	}

	/**
	 * @dataProvider versions_with_migration_applied_provider
	 */
	public function test_it_does_nothing_if_migration_was_already_applied( string $stored_version ) {
		update_option( 'woocommerce_woocommerce_payments_version', $stored_version );
		$this->set_up_settings( 'yes', [ 'card', 'link' ] );

		$this->expect_no_changes();

		$this->migration->maybe_migrate();
	}

	public function versions_with_migration_applied_provider(): array {
		return [
			'same version' => [ '11.4.0' ],
			'newer'        => [ '11.5.0' ],
		];
	}

	/**
	 * @param string $stripe_id The gateway's Stripe payment method ID.
	 *
	 * @return WC_Payment_Gateway_WCPay|MockObject
	 */
	private function create_gateway_mock( string $stripe_id ) {
		$gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$gateway->method( 'get_stripe_id' )->willReturn( $stripe_id );

		return $gateway;
	}

	private function set_up_settings( string $woopay_setting, array $enabled_payment_method_ids ) {
		$this->card_gateway->method( 'get_option' )
			->with( 'platform_checkout', 'no' )
			->willReturn( $woopay_setting );
		$this->card_gateway->method( 'get_upe_enabled_payment_method_ids' )->willReturn( $enabled_payment_method_ids );
	}

	private function expect_no_changes() {
		foreach ( [ $this->card_gateway, $this->link_gateway, $this->sepa_gateway ] as $gateway ) {
			$gateway->expects( $this->never() )->method( 'disable' );
			$gateway->expects( $this->never() )->method( 'update_option' );
		}
	}
}
