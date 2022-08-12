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

	public function test_get_orders_from_charge_ids() {
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

		$orders_with_charge_ids = $this->wcpay_db->orders_from_charge_ids( array_merge( $existing_charge_ids, $non_existing_charge_ids ) );

		$this->assertCount( 2, $orders_with_charge_ids );
		$this->assertSame( 'ch_1', $orders_with_charge_ids[0]->get_meta( '_charge_id' ) );
		$this->assertSame( 'ch_2', $orders_with_charge_ids[1]->get_meta( '_charge_id' ) );
	}

}
