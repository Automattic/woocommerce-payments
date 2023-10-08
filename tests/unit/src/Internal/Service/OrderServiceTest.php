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
use WC_Payments_API_Charge;
use WC_Payments_API_Payment_Intention;
use WC_Payments_API_Setup_Intention;
use WC_Payments_Features;
use WC_Payments_Order_Service;
use WCPay\Constants\Payment_Type;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\PaymentMethod\NewPaymentMethod;
use WCPay\Internal\Proxy\HooksProxy;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPAY_UnitTestCase;
use WCPay\Internal\Service\OrderService;
use WP_User;

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

		$this->mock_legacy_proxy   = $this->createMock( LegacyProxy::class );
		$this->mock_legacy_service = $this->createMock( WC_Payments_Order_Service::class );
		$this->mock_account        = $this->createMock( WC_Payments_Account::class );
		$this->mock_hooks_proxy    = $this->createMock( HooksProxy::class );

		// Service under test, but with mockable methods.
		$this->sut = $this->getMockBuilder( OrderService::class )
			->onlyMethods( [ 'get_order', 'attach_exchange_info_to_order' ] )
			->setConstructorArgs(
				[
					$this->mock_legacy_service,
					$this->mock_legacy_proxy,
					$this->mock_account,
					$this->mock_hooks_proxy,
				]
			)
			->getMock();
	}

	public function test_get_order_returns_order() {
		$this->sut = new OrderService(
			$this->mock_legacy_service,
			$this->mock_legacy_proxy,
			$this->mock_account,
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
			$this->mock_legacy_service,
			$this->mock_legacy_proxy,
			$this->mock_account,
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

	public function provider_import_order_data_to_payment_context() {
		$existing_user     = new WP_User();
		$existing_user->ID = 10;

		return [
			'No User' => [ null ],
			'User'    => [ $existing_user ],
		];
	}

	/**
	 * @dataProvider provider_import_order_data_to_payment_context
	 */
	public function test_import_order_data_to_payment_context( $user ) {
		// Create a mock order that will be used to extract data.
		$mock_order = $this->createMock( WC_Order::class );
		$this->sut->expects( $this->once() )
			->method( 'get_order' )
			->willReturn( $mock_order );

		// Create a context where data will be imported.
		$mock_context = $this->createMock( PaymentContext::class );

		// Currency and amount calls.
		$currency = 'usd';
		$mock_order->expects( $this->once() )->method( 'get_currency' )->willReturn( $currency );
		$mock_context->expects( $this->once() )->method( 'set_currency' )->with( $currency );
		$amount = 1234;
		$mock_order->expects( $this->once() )->method( 'get_total' )->willReturn( $amount / 100 );
		$mock_context->expects( $this->once() )->method( 'set_amount' )->with( $amount );

		// Mock the user.
		$mock_order->expects( $this->once() )
			->method( 'get_user' )
			->willReturn( $user ?? false );
		if ( ! $user ) {
			$user     = $this->createMock( WP_User::class );
			$user->ID = 10;

			$this->mock_legacy_proxy->expects( $this->once() )
				->method( 'call_function' )
				->with( 'wp_get_current_user' )
				->willReturn( $user );
		}
		$mock_context->expects( $this->once() )
			->method( 'set_user_id' )
			->with( 10 );

		// Act.
		$this->sut->import_order_data_to_payment_context( $this->order_id, $mock_context );
	}

	public function provider_update_order_from_successful_intent() {
		$pi = $this->createMock( WC_Payments_API_Payment_Intention::class );
		$si = $this->createMock( WC_Payments_API_Setup_Intention::class );

		return [
			'Payment Intent' => [ $pi ],
			'Setup Intent'   => [ $si ],
		];
	}

	/**
	 * @param WC_Payments_API_Payment_Intention|WC_Payments_API_Setup_Intention|MockObject $intent
	 * @dataProvider provider_update_order_from_successful_intent
	 */
	public function test_update_order_from_successful_intent( $intent ) {
		$charge_id         = null;
		$mock_charge       = null;
		$intent_id         = 'pi_XYZ';
		$intent_status     = 'success';
		$customer_id       = 'cus_XYZ';
		$currency          = 'usd';
		$payment_method_id = 'pm_XYZ';

		// Create a mock order that will be used.
		$mock_order = $this->createMock( WC_Order::class );
		$this->sut->expects( $this->once() )
			->method( 'get_order' )
			->with( $this->order_id )
			->willReturn( $mock_order );

		if ( is_a( $intent, WC_Payments_API_Payment_Intention::class ) ) {
			$charge_id   = 'ch_XYZ';
			$mock_charge = $this->createMock( WC_Payments_API_Charge::class );

			$mock_charge->expects( $this->once() )
				->method( 'get_id' )
				->willReturn( $charge_id );

			$intent->expects( $this->exactly( 2 ) )
				->method( 'get_charge' )
				->willReturn( $mock_charge );
		}

		// Prepare all parameters for `attach_intent_info_to_order`.
		$intent->expects( $this->once() )
			->method( 'get_id' )
			->willReturn( $intent_id );
		$intent->expects( $this->once() )
			->method( 'get_status' )
			->willReturn( $intent_status );

		$mock_context = $this->createMock( PaymentContext::class );
		$mock_context->expects( $this->once() )
			->method( 'get_payment_method' )
			->willReturn( new NewPaymentMethod( $payment_method_id ) );
		$mock_context->expects( $this->once() )
			->method( 'get_customer_id' )
			->willReturn( $customer_id );
		$mock_context->expects( $this->once() )
			->method( 'get_currency' )
			->willReturn( $currency );

		$this->mock_legacy_service->expects( $this->once() )
			->method( 'attach_intent_info_to_order' )
			->with(
				$mock_order,
				$intent_id,
				$intent_status,
				$payment_method_id,
				$customer_id,
				$charge_id,
				$currency
			);

		// Prepare all additional calls.
		$this->mock_legacy_service->expects( $this->once() )
			->method( 'attach_transaction_fee_to_order' )
			->with( $mock_order, $mock_charge );
		$this->mock_legacy_service->expects( $this->once() )
			->method( 'update_order_status_from_intent' )
			->with( $mock_order, $intent );
		if ( ! is_null( $mock_charge ) ) {
			$this->sut->expects( $this->once() )
				->method( 'attach_exchange_info_to_order' )
				->with( $this->order_id, $mock_charge );
		}

		// Act.
		$this->sut->update_order_from_successful_intent( $this->order_id, $intent, $mock_context );
	}

	public function provider_attach_exchange_info_to_order() {
		return [
			'Different store and account currencies' => [ 'USD', 'USD', 'EUR', null, null ],
			'Same order and account currencies'      => [ 'EUR', 'EUR', 'EUR', null, null ],
			'No exchange rate'                       => [ 'USD', 'EUR', 'USD', true, null ],
			'With exchange rate'                     => [ 'USD', 'EUR', 'USD', true, 3.0 ],
		];
	}

	/**
	 * @dataProvider provider_attach_exchange_info_to_order
	 */
	public function test_attach_exchange_info_to_order( $store_currency, $order_currency, $account_currency, $has_charge = false, $exchange_rate = null ) {
		/**
		 * Create a SUT that doesn't mock the method here.
		 *
		 * @var OrderService|MockObject
		 */
		$this->sut = $this->getMockBuilder( OrderService::class )
			->onlyMethods( [ 'get_order' ] )
			->setConstructorArgs(
				[
					$this->mock_legacy_service,
					$this->mock_legacy_proxy,
					$this->mock_account,
					$this->mock_hooks_proxy,
				]
			)
			->getMock();

		// Mock the store currency.
		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'get_option', 'woocommerce_currency' )
			->willReturn( $store_currency );

		// Mock the order currency.
		$mock_order = $this->mock_get_order();
		$mock_order->expects( $this->once() )->method( 'get_currency' )->willReturn( $order_currency );

		// Mock the account currency.
		$this->mock_account->expects( $this->once() )
			->method( 'get_account_default_currency' )
			->willReturn( $account_currency );

		// No charge means that the charge object should never be reached.
		$mock_charge = $this->createMock( WC_Payments_API_Charge::class );
		if ( ! $has_charge ) {
			$mock_charge->expects( $this->never() )->method( 'get_balance_transaction' );
			$this->sut->attach_exchange_info_to_order( $this->order_id, $mock_charge );
			return;
		}

		$transaction = [ 'exchange_rate' => $exchange_rate ];
		$mock_charge->expects( $this->once() )
			->method( 'get_balance_transaction' )
			->willReturn( $transaction );

		// No exchange rate means that the order will never be updated.
		if ( ! $exchange_rate ) {
			$mock_order->expects( $this->never() )->method( 'update_meta_data' );
			$this->sut->attach_exchange_info_to_order( $this->order_id, $mock_charge );
			return;
		}

		$mock_order->expects( $this->once() )
			->method( 'update_meta_data' )
			->with( '_wcpay_multi_currency_stripe_exchange_rate', $exchange_rate );
		$mock_order->expects( $this->once() )
			->method( 'save_meta_data' );

		// Act.
		$this->sut->attach_exchange_info_to_order( $this->order_id, $mock_charge );
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
