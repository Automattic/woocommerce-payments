<?php
/**
 * Class Update_Service_Data_From_Server_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Migrations;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

/**
 * WCPay\Migrations\Update_Service_Data_From_Server unit tests.
 */
class Update_Service_Data_From_Server_Test extends WP_UnitTestCase {

	/**
	 * WCPay gateway mock.
	 *
	 * @var MockObject|WC_Payments_Account
	 */
	private $account_mock;

	/**
	 * @var Update_Service_Data_From_Server
	 */
	private $migration;

	public function setUp() {
		$this->account_mock = $this->getMockBuilder( \WC_Payments_Account::class )->disableOriginalConstructor()->setMethods( [ 'get_cached_account_data', 'refresh_account_data' ] )->getMock();
		$this->migration    = new Update_Service_Data_From_Server( $this->account_mock );
	}

	/**
	 * @dataProvider empty_account_cache_values_data_provider
	 */
	public function test_it_does_nothing_if_account_data_is_empty( $empty_value ) {
		$this->account_mock->expects( $this->atLeastOnce() )->method( 'get_cached_account_data' )->will( $this->returnValue( $empty_value ) );
		$this->account_mock->expects( $this->never() )->method( 'refresh_account_data' );

		$this->migration->maybe_migrate();
	}

	public function test_does_nothing_if_account_data_contains_giropay_fees() {
		$this->account_mock->expects( $this->atLeastOnce() )->method( 'get_cached_account_data' )->will(
			$this->returnValue(
				[
					'account_id' => 'acct_xxxxx',
					'is_live'    => true,
					'fees'       => [
						'base'     => [
							'percentage_rate' => 0.029,
							'fixed_rate'      => 30,
							'currency'        => 'usd',
						],
						'discount' => [],
						'card'     =>
							[
								'base'     => [
									'percentage_rate' => 0.029,
									'fixed_rate'      => 30,
									'currency'        => 'usd',
								],
								'discount' => [],
							],
						'giropay'  =>
							[
								'base'     => [
									'percentage_rate' => 0.014,
									'fixed_rate'      => 30,
									'currency'        => 'usd',
								],
								'discount' => [],
							],
					],
					'country'    => 'US',
				]
			)
		);
		$this->account_mock->expects( $this->never() )->method( 'refresh_account_data' );

		$this->migration->maybe_migrate();
	}

	public function test_updates_service_data_if_account_data_does_not_contain_giropay_fees() {
		$this->account_mock->expects( $this->atLeastOnce() )->method( 'get_cached_account_data' )->will(
			$this->returnValue(
				[
					'account_id' => 'acct_xxxxx',
					'is_live'    => true,
					'fees'       => [
						'base'     => [
							'percentage_rate' => 0.029,
							'fixed_rate'      => 30,
							'currency'        => 'usd',
						],
						'discount' => [],
						'card'     =>
							[
								'base'     => [
									'percentage_rate' => 0.029,
									'fixed_rate'      => 30,
									'currency'        => 'usd',
								],
								'discount' => [],
							],
					],
					'country'    => 'US',
				]
			)
		);
		$this->account_mock->expects( $this->atLeastOnce() )->method( 'refresh_account_data' );

		$this->migration->maybe_migrate();
	}

	public function empty_account_cache_values_data_provider() {
		return [
			'server not connected'                => [ [] ],
			'account retrieval error or dev mode' => [ false ],
		];
	}
}
