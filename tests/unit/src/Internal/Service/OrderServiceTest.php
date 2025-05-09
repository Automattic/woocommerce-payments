<?php
/**
 * Class OrderServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Order;
use WC_Payments_Features;
use WCPay\Constants\Payment_Type;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Internal\Payment\PaymentMethod\NewPaymentMethod;
use WCPay\Internal\Proxy\HooksProxy;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPAY_UnitTestCase;
use WCPay\Internal\Service\OrderService;

/**
 * Order service unit tests.
 */
class OrderServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var MockObject|OrderService
	 */
	private $sut;

	/**
	 * @var LegacyProxy|MockObject
	 */
	private $mock_legacy_proxy;

	/**
	 * @var HooksProxy|MockObject
	 */
	private $mock_hooks_proxy;

	/**
	 * Order ID used for mocks.
	 *
	 * @var int
	 */
	private $order_id = 123;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_legacy_proxy = $this->createMock( LegacyProxy::class );
		$this->mock_hooks_proxy  = $this->createMock( HooksProxy::class );

		// Service under test, but with mockable methods.
		$this->sut = $this->getMockBuilder( OrderService::class )
			->onlyMethods( [ 'get_order' ] )
			->setConstructorArgs(
				[
					$this->mock_legacy_proxy,
					$this->mock_hooks_proxy,
				]
			)
			->getMock();
	}

	public function test_get_order_returns_order() {
		$this->sut = new OrderService(
			$this->mock_legacy_proxy,
			$this->mock_hooks_proxy
		);

		$mock_order = $this->createMock( WC_Order::class );
		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'wc_get_order', $this->order_id )
			->willReturn( $mock_order );

		// Go through `_deprecated_get_order` to call `get_order`.
		$result = $this->sut->_deprecated_get_order( $this->order_id );
		$this->assertSame( $mock_order, $result );
	}

	public function test_get_order_throws_exception() {
		$this->sut = new OrderService(
			$this->mock_legacy_proxy,
			$this->mock_hooks_proxy
		);

		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'wc_get_order', $this->order_id )
			->willReturn( false );

		$this->expectException( Order_Not_Found_Exception::class );
		$this->expectExceptionMessage( "The requested order (ID $this->order_id) was not found." );

		// Go through `_deprecated_get_order` to call `get_order`.
		$this->sut->_deprecated_get_order( $this->order_id );
	}

	public function test__deprecated_get_order_returns_order() {
		$mock_order = $this->mock_get_order();

		$result = $this->sut->_deprecated_get_order( $this->order_id );
		$this->assertSame( $mock_order, $result );
	}

	public function test_get_payment_metadata_without_subscriptions() {
		// Prepare data and expectations.
		$first_name   = 'John';
		$last_name    = 'Doe';
		$email        = 'example@example.com';
		$order_number = 'ABC123';
		$order_key    = 'xyz123';
		$created_via  = 'checkout';

		$expected = [
			'customer_name'        => $first_name . ' ' . $last_name,
			'customer_email'       => $email,
			'site_url'             => esc_url( get_site_url() ),
			'order_id'             => $this->order_id,
			'order_number'         => $order_number,
			'order_key'            => $order_key,
			'payment_type'         => 'single',
			'checkout_type'        => $created_via,
			'client_version'       => WCPAY_VERSION_NUMBER,
			'subscription_payment' => 'no',
		];

		// Setup the mock order.
		$mock_order = $this->mock_get_order();

		$order_methods = [
			'get_id'                 => $this->order_id,
			'get_billing_first_name' => $first_name,
			'get_billing_last_name'  => $last_name,
			'get_billing_email'      => $email,
			'get_order_number'       => $order_number,
			'get_order_key'          => $order_key,
			'get_created_via'        => $created_via,
		];
		foreach ( $order_methods as $name => $value ) {
			$mock_order->expects( $this->once() )
				->method( $name )
				->willReturn( $value );
		}

		// Expect filters.
		$this->mock_hooks_proxy->expects( $this->once() )
			->method( 'apply_filters' )
			->with( 'wcpay_metadata_from_order', $expected, $mock_order, Payment_Type::SINGLE() )
			->willReturn( $expected );

		// Act.
		$result = $this->sut->get_payment_metadata( $this->order_id, Payment_Type::SINGLE() );

		// Assert.
		$this->assertEquals( $expected, $result );
	}

	/**
	 * @dataProvider provider_subscription_details
	 */
	public function test_get_payment_metadata_with_subscription( bool $is_renewal, bool $wcpay_subscription ) {
		$mock_order = $this->mock_get_order();

		$this->mock_legacy_proxy->expects( $this->exactly( 3 ) )
			->method( 'call_function' )
			->withConsecutive(
				[ 'function_exists', 'wcs_order_contains_subscription' ],
				[ 'wcs_order_contains_subscription', $mock_order, 'any' ],
				[ 'wcs_order_contains_renewal', $mock_order ]
			)
			->willReturnOnConsecutiveCalls( true, true, $is_renewal );

		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_static' )
			->with( WC_Payments_Features::class, 'should_use_stripe_billing' )
			->willReturn( $wcpay_subscription );

		// Expect filters.
		$this->mock_hooks_proxy->expects( $this->once() )
			->method( 'apply_filters' )
			->with( 'wcpay_metadata_from_order', $this->callback( 'is_array' ), $mock_order, Payment_Type::RECURRING() )
			->willReturnArgument( 1 );

		// Act.
		$result = $this->sut->get_payment_metadata( $this->order_id, Payment_Type::RECURRING() );

		// Assert.
		$this->assertIsArray( $result );
		$this->assertEquals( $is_renewal ? 'renewal' : 'initial', $result['subscription_payment'] );
		$this->assertEquals( $wcpay_subscription ? 'wcpay_subscription' : 'regular_subscription', $result['payment_context'] );
	}

	public function provider_subscription_details() {
		return [
			// is_renewal and wcpay_subscription.
			[ false, false ],
			[ false, true ],
			[ true, false ],
			[ true, true ],
		];
	}

	public function provider_get_intent_id() {
		return [
			'No attached intent'    => [ null, null ],
			'Empty string attached' => [ '', null ],
			'Intent ID attached'    => [ 'pi_123', 'pi_123' ],
		];
	}

	/**
	 * @dataProvider provider_get_intent_id
	 */
	public function test_get_intent_id( $meta_value, $expected ) {
		$this->mock_get_order()
			->expects( $this->once() )
			->method( 'get_meta' )
			->with( '_intent_id' )
			->willReturn( $meta_value );

		$result = $this->sut->get_intent_id( $this->order_id );
		$this->assertSame( $expected, $result );
	}

	public function test_get_cart_hash() {
		$this->mock_get_order()
			->expects( $this->once() )
			->method( 'get_cart_hash' )
			->willReturn( 'abc123' );

		$result = $this->sut->get_cart_hash( $this->order_id );
		$this->assertSame( 'abc123', $result );
	}

	public function test_get_customer_id() {
		$customer_id = 123456;

		$this->mock_get_order()
			->expects( $this->once() )
			->method( 'get_customer_id' )
			->willReturn( $customer_id );

		$result = $this->sut->get_customer_id( $this->order_id );
		$this->assertSame( $customer_id, $result );
	}

	public function test_is_paid() {
		$paid_statuses = [ 'processing', 'completed' ];
		$expected      = true;

		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'wc_get_is_paid_statuses' )
			->willReturn( $paid_statuses );

		$this->mock_get_order()
			->expects( $this->once() )
			->method( 'has_status' )
			->with( $paid_statuses )
			->willReturn( $expected );

		$result = $this->sut->is_paid( $this->order_id );
		$this->assertSame( $expected, $result );
	}

	public function test_is_pending() {
		$pending_statuses = [ 'pending' ];
		$expected         = false;

		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'wc_get_is_pending_statuses' )
			->willReturn( $pending_statuses );

		$this->mock_get_order()
			->expects( $this->once() )
			->method( 'has_status' )
			->with( $pending_statuses )
			->willReturn( $expected );

		$result = $this->sut->is_pending( $this->order_id );
		$this->assertSame( $expected, $result );
	}

	public function provider_is_valid_phone_number(): array {
		return [
			'valid phone number'                         => [ '1234567890', true ],
			'invalid phone number - more than 20 digits' => [ '123456789012345678901', false ],
		];
	}

	/**
	 * @dataProvider provider_is_valid_phone_number
	 */
	public function test_is_valid_phone_number( $phone_number, $expected ) {
		$this->mock_get_order()
			->expects( $this->once() )
			->method( 'get_billing_phone' )
			->willReturn( $phone_number );

		$result = $this->sut->is_valid_phone_number( $this->order_id );
		$this->assertSame( $expected, $result );
	}

	public function test_add_note() {
		$note_id      = 321;
		$note_content = 'Note content';

		$this->mock_get_order()
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with( $note_content )
			->willReturn( $note_id );

		$result = $this->sut->add_note( $this->order_id, $note_content );
		$this->assertSame( $note_id, $result );
	}

	public function test_delete_order() {
		$force_delete = false;
		$expected     = true;

		$this->mock_get_order()
			->expects( $this->once() )
			->method( 'delete' )
			->with( $force_delete )
			->willReturn( $expected );

		$result = $this->sut->delete( $this->order_id, $force_delete );
		$this->assertSame( $expected, $result );
	}

	public function test_set_mode() {
		$this->mock_get_order()
			->expects( $this->once() )
			->method( 'update_meta_data' )
			->with( '_wcpay_mode', 'prod' );
		$this->sut->set_mode( $this->order_id, 'prod' );
	}

	public function test_get_mode() {
		$this->mock_get_order()
			->expects( $this->once() )
			->method( 'get_meta' )
			->with( '_wcpay_mode', true )
			->willReturn( 'test' );
		$result = $this->sut->get_mode( $this->order_id, true );
		$this->assertSame( 'test', $result );
	}

	/**
	 * Mocks order retrieval.
	 *
	 * @param int $order_id ID of the order to mock.
	 * @return WC_Order|MockObject The mock order, ready for setup.
	 */
	private function mock_get_order( int $order_id = null ) {
		$order_id   = $order_id ?? $this->order_id;
		$mock_order = $this->createMock( WC_Order::class );

		$this->sut->expects( $this->once() )
			->method( 'get_order' )
			->with( $order_id )
			->willReturn( $mock_order );

		return $mock_order;
	}
}
