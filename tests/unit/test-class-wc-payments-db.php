<?php
/**
 * Class WC_Payments_DB_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_DB unit tests.
 */
class WC_Payments_DB_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Payments_DB
	 */
	private $wcpay_db;


	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->wcpay_db = new WC_Payments_DB();
	}

	public function test_get_orders_with_charge_id_from_charge_ids() {
		$existing_charge_ids = [
			'ch_1',
			'ch_2',
		];

		$non_existing_charge_ids = [
			'ch_3',
		];

		foreach ( $existing_charge_ids as $charge_id ) {
			$order = WC_Helper_Order::create_order();
			$order->update_meta_data( '_charge_id', $charge_id );
			$order->save();
		}

		$orders_with_charge_ids = $this->wcpay_db->orders_with_charge_id_from_charge_ids( array_merge( $existing_charge_ids, $non_existing_charge_ids ) );

		$this->assertCount( 2, $orders_with_charge_ids );
		$this->assertIsArray( $orders_with_charge_ids[0] );
		$this->assertSame( 'ch_1', $orders_with_charge_ids[0]['charge_id'] );
		$this->assertIsArray( $orders_with_charge_ids[1] );
		$this->assertSame( 'ch_2', $orders_with_charge_ids[1]['charge_id'] );
	}
}
