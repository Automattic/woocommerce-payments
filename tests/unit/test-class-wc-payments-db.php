<?php
/**
 * Class WC_Payments_DB_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_DB unit tests.
 */
class WC_Payments_DB_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WC_Payments_DB
	 */
	private $wcpay_db;


	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->wcpay_db = new WC_Payments_DB();
	}

	public function test_get_orders_with_charge_id_from_charge_ids() {
		$existing_charge_ids = [
			'ch_1',
			'ch_2',
			'ch_3',
			'ch_4',
			'ch_5',
			'ch_6',
			'ch_7',
			'ch_8',
			'ch_9',
			'ch_10',
			'ch_11',
		];

		$non_existing_charge_ids = [
			'ch_12',
		];

		foreach ( $existing_charge_ids as $charge_id ) {
			$order = WC_Helper_Order::create_order();
			$order->update_meta_data( '_charge_id', $charge_id );
			$order->save();
		}

		$orders_with_charge_ids = $this->wcpay_db->orders_with_charge_id_from_charge_ids( array_merge( $existing_charge_ids, $non_existing_charge_ids ) );

		$this->assertCount( 11, $orders_with_charge_ids );
		$this->assertIsArray( $orders_with_charge_ids[0] );
		$this->assertTrue( in_array( $orders_with_charge_ids[0]['charge_id'], $existing_charge_ids, true ) );
		$this->assertIsArray( $orders_with_charge_ids[1] );
		$this->assertTrue( in_array( $orders_with_charge_ids[1]['charge_id'], $existing_charge_ids, true ) );
	}

}
