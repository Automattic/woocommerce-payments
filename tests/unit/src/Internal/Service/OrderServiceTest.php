<?php
/**
 * Class OrderServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Order;
use WC_Payments_Account;
use WC_Payments_Features;
use WC_Payments_Order_Service;
use WCPay\Constants\Payment_Type;
use WCPay\Exceptions\Order_Not_Found_Exception;
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
	 * @var OrderService
	 */
	private $sut;

	/**
	 * Service under test, but with mockable `get_order`.
	 *
	 * @var OrderService|MockObject
	 */
	private $mock_sut;

	/**
	 * @var WC_Payments_Order_Service|MockObject
	 */
	private $mock_legacy_service;

	/**
	 * @var LegacyProxy|MockObject
	 */
	private $mock_legacy_proxy;

	/**
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_account;

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

		$this->mock_legacy_proxy   = $this->createMock( LegacyProxy::class );
		$this->mock_legacy_service = $this->createMock( WC_Payments_Order_Service::class );
		$this->mock_account        = $this->createMock( WC_Payments_Account::class );

		// Main service under test: OrderService.
		$this->sut = new OrderService( $this->mock_legacy_service, $this->mock_legacy_proxy, $this->mock_account );

		// Same service under test, but with a mockable `get_order`.
		$this->mock_sut = $this->getMockBuilder( OrderService::class )
			->onlyMethods( [ 'get_order' ] )
			->setConstructorArgs(
				[
					$this->mock_legacy_service,
					$this->mock_legacy_proxy,
					$this->mock_account,
				]
			)
			->getMock();
	}

	public function test_get_order_returns_order() {
		$mock_order = $this->createMock( WC_Order::class );

		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'wc_get_order', $this->order_id )
			->willReturn( $mock_order );

		$result = $this->sut->get_order( $this->order_id );
		$this->assertSame( $mock_order, $result );
	}

	public function test_get_order_throws_exception() {
		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'wc_get_order', $this->order_id )
			->willReturn( false );

		$this->expectException( Order_Not_Found_Exception::class );
		$this->expectExceptionMessage( 'The requested order was not found.' );
		$this->sut->get_order( $this->order_id );
	}

	public function test_set_payment_method_id() {
		$pm_id = 'pm_XYZ';

		$this->mock_legacy_service->expects( $this->once() )
			->method( 'set_payment_method_id_for_order' )
			->with( $this->order_id, $pm_id );

		$this->sut->set_payment_method_id( $this->order_id, $pm_id );
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
		$mock_order = $this->createMock( WC_Order::class );
		$this->mock_sut->expects( $this->once() )
			->method( 'get_order' )
			->with( $this->order_id )
			->willReturn( $mock_order );

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

		// Act.
		$result = $this->mock_sut->get_payment_metadata( $this->order_id, Payment_Type::SINGLE() );

		// Assert.
		$this->assertEquals( $expected, $result );
	}

	/**
	 * @dataProvider provider_subscription_details
	 */
	public function test_get_payment_metadata_with_subscription( bool $is_renewal, bool $wcpay_subscription ) {
		$mock_order = $this->createMock( WC_Order::class );
		$this->mock_sut->expects( $this->once() )
			->method( 'get_order' )
			->with( $this->order_id )
			->willReturn( $mock_order );

		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'function_exists' )
			->with( 'wcs_order_contains_subscription' )
			->willReturn( true );

		$this->mock_legacy_proxy->expects( $this->exactly( 2 ) )
			->method( 'call_function' )
			->withConsecutive(
				[ 'wcs_order_contains_subscription', $mock_order, 'any' ],
				[ 'wcs_order_contains_renewal', $mock_order ]
			)
			->willReturnOnConsecutiveCalls( true, $is_renewal );

		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_static' )
			->with( WC_Payments_Features::class, 'should_use_stripe_billing' )
			->willReturn( $wcpay_subscription );

		// Act.
		$result = $this->mock_sut->get_payment_metadata( $this->order_id, Payment_Type::RECURRING() );

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
}
