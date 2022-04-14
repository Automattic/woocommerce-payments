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
	public function set_up() {
		parent::set_up();

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

	public function test_get_last_active_users_when_timestamp_is_passed() {
		$user = wp_create_user( wp_generate_uuid4(), wp_generate_password() );
		update_user_meta( $user, 'wc_last_active', 1000 ); // Dummy timestamp value.
		$users = $this->wcpay_db->get_last_active_users( 900 );
		$this->assertIsArray( $users );
		$this->assertCount( 1, $users );
		$this->assertArrayHasKey( 'user_id', $users[0] );
		$this->assertSame( $user, (int) $users[0]['user_id'] );
	}

	public function test_get_last_active_users_when_timestamp_is_not_passed() {
		$user = wp_create_user( wp_generate_uuid4(), wp_generate_password() );
		update_user_meta( $user, 'wc_last_active', strtotime( '-1 hour' ) );
		$users = $this->wcpay_db->get_last_active_users();
		$this->assertIsArray( $users );
		$this->assertCount( 1, $users );
		$this->assertArrayHasKey( 'user_id', $users[0] );
		$this->assertSame( $user, (int) $users[0]['user_id'] );
	}

	public function test_get_last_active_users_will_not_return_past_active_users() {
		$user = wp_create_user( wp_generate_uuid4(), wp_generate_password() );
		update_user_meta( $user, 'wc_last_active', strtotime( '-1 week' ) );
		$users = $this->wcpay_db->get_last_active_users();
		$this->assertIsArray( $users );
		$this->assertCount( 0, $users );
	}
}
