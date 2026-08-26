<?php
/**
 * These tests make assertions against class WC_Payments_WooPay_Direct_Checkout.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\WooPay_Utilities;

/**
 * WC_Payments_WooPay_Direct_Checkout_Test class.
 */
class WC_Payments_WooPay_Direct_Checkout_Test extends WCPAY_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payments_WooPay_Direct_Checkout
	 */
	private $direct_checkout;

	public function set_up() {
		parent::set_up();

		// The filter only runs during checkout. Rather than define the global
		// WOOCOMMERCE_CHECKOUT constant (which cannot be undefined and would leak into other
		// test classes), stub is_checkout_request() to report a checkout request.
		$this->direct_checkout = $this->getMockBuilder( WC_Payments_WooPay_Direct_Checkout::class )
			->setConstructorArgs( [ $this->createMock( WooPay_Utilities::class ) ] )
			->onlyMethods( [ 'is_checkout_request' ] )
			->getMock();
		$this->direct_checkout->method( 'is_checkout_request' )->willReturn( true );

		WC()->session->set( 'store_api_draft_order', null );
		WC()->session->set( 'order_awaiting_payment', null );
	}

	public function tear_down() {
		WC()->session->set( 'store_api_draft_order', null );
		WC()->session->set( 'order_awaiting_payment', null );

		parent::tear_down();
	}

	/**
	 * A session pointing at a draft order that no longer exists must not fatal:
	 * the stale pointer is discarded and checkout falls through to normal order creation.
	 */
	public function test_stale_draft_order_id_is_discarded_without_fatal() {
		$non_existent_order_id = 999999999;
		WC()->session->set( 'store_api_draft_order', $non_existent_order_id );

		$result = $this->direct_checkout->maybe_use_store_api_draft_order_id( 0 );

		$this->assertSame( 0, $result );
		$this->assertNull( WC()->session->get( 'store_api_draft_order' ) );
		$this->assertNull( WC()->session->get( 'order_awaiting_payment' ) );
	}

	/**
	 * Under HPOS a reused post ID can make the stale pointer resolve to a non-order post
	 * (e.g. a media attachment). wc_get_order() returns false for such a post, and the
	 * instance check must keep checkout from fataling on set_status().
	 */
	public function test_draft_order_id_resolving_to_non_order_post_is_discarded_without_fatal() {
		$attachment_id = self::factory()->post->create( [ 'post_type' => 'attachment' ] );
		WC()->session->set( 'store_api_draft_order', $attachment_id );

		$result = $this->direct_checkout->maybe_use_store_api_draft_order_id( 0 );

		$this->assertSame( 0, $result );
		$this->assertNull( WC()->session->get( 'store_api_draft_order' ) );
		$this->assertNull( WC()->session->get( 'order_awaiting_payment' ) );
	}

	/**
	 * A valid draft order is still resumed: its status is set to pending and the session
	 * pointer is moved from store_api_draft_order to order_awaiting_payment.
	 */
	public function test_valid_draft_order_is_resumed() {
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'checkout-draft' );
		$order->save();

		WC()->session->set( 'store_api_draft_order', $order->get_id() );

		$result = $this->direct_checkout->maybe_use_store_api_draft_order_id( 0 );

		$this->assertSame( 0, $result );
		$this->assertSame( 'pending', wc_get_order( $order->get_id() )->get_status() );
		$this->assertNull( WC()->session->get( 'store_api_draft_order' ) );
		$this->assertEquals( $order->get_id(), WC()->session->get( 'order_awaiting_payment' ) );
	}

	/**
	 * A trashed order still resolves to a WC_Order, but is not a resumable draft, so it must be
	 * discarded rather than resumed. Guards against the "just trashed" case raised in review.
	 */
	public function test_trashed_order_is_discarded_and_not_resumed() {
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'checkout-draft' );
		$order->save();
		$order->get_data_store()->delete( $order, [ 'force_delete' => false ] ); // Trash, not force-delete.

		WC()->session->set( 'store_api_draft_order', $order->get_id() );

		$result = $this->direct_checkout->maybe_use_store_api_draft_order_id( 0 );

		$this->assertSame( 0, $result );
		$this->assertNull( WC()->session->get( 'store_api_draft_order' ) );
		$this->assertNull( WC()->session->get( 'order_awaiting_payment' ) );
	}
}
