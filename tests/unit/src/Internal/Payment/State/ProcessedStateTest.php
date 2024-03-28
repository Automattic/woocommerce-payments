<?php
/**
 * Class ProcessedStateTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Helper_Intention;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Payment\State\ProcessedState;
use WCPay\Internal\Payment\State\StateFactory;
use WCPay\Internal\Service\DuplicatePaymentPreventionService;
use WCPay\Internal\Service\OrderService;
use WCPay\Internal\Proxy\LegacyProxy;
use WC_Payment_Gateway_WCPay;
use WCPAY_UnitTestCase;

/**
 * Tests for the processed payment state.
 */
class ProcessedStateTest extends WCPAY_UnitTestCase {
	/**
	 * State under test.
	 *
	 * @var ProcessedState
	 */
	private $sut;

	/**
	 * @var StateFactory|MockObject
	 */
	private $mock_state_factory;

	/**
	 * @var OrderService|MockObject
	 */
	private $mock_order_service;

	/**
	 * @var DuplicatePaymentPreventionService|MockObject
	 */
	private $mock_dpps;

	/**
	 * @var PaymentContext|MockObject
	 */
	private $mock_context;

	/**
	 * @var LegacyProxy|MockObject
	 */
	private $mock_legacy_proxy;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_state_factory = $this->createMock( StateFactory::class );
		$this->mock_order_service = $this->createMock( OrderService::class );
		$this->mock_dpps          = $this->createMock( DuplicatePaymentPreventionService::class );
		$this->mock_context       = $this->createMock( PaymentContext::class );
		$this->mock_legacy_proxy  = $this->createMock( LegacyProxy::class );

		$this->sut = new ProcessedState(
			$this->mock_state_factory,
			$this->mock_order_service,
			$this->mock_dpps,
			$this->mock_legacy_proxy
		);
		$this->sut->set_context( $this->mock_context );
	}

	public function test_complete_processing_will_transition_to_completed_state() {
		$intent = WC_Helper_Intention::create_intention();

		$this->mock_context->expects( $this->once() )
			->method( 'get_intent' )
			->willReturn( $intent );

		$this->mock_context->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( 1 );

		$this->mock_order_service->expects( $this->once() )
			->method( 'update_order_from_successful_intent' )
			->with( 1, $intent );

		$this->mock_dpps->expects( $this->once() )
			->method( 'remove_session_processing_order' )
			->with( 1 );

		$mock_completed_state = $this->createMock( CompletedState::class );

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( CompletedState::class, $this->mock_context )
			->willReturn( $mock_completed_state );

		$this->mock_legacy_proxy
			->expects( $this->exactly( 2 ) )
			->method( 'call_function' )
			->withConsecutive( [ 'wc_reduce_stock_levels', 1 ], [ 'wc' ] )
			->willReturnCallback(
				function ( $arg ) {
					if ( 'wc' === $arg ) {
						$mock_cart = $this->getMockBuilder( \stdClass::class )
							->addMethods( [ 'empty_cart' ] )
							->getMock();
							return (object) [
								'cart' => $mock_cart,
							];
					}
				}
			);

		$result = $this->sut->complete_processing();

		$this->assertSame( $mock_completed_state, $result );
	}

	public function test_clean_up_functions() {

		// setup.
		$intent = WC_Helper_Intention::create_intention();

		$this->mock_context->expects( $this->once() )
			->method( 'get_intent' )
			->willReturn( $intent );

		$this->mock_context->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( 1 );

		$mock_completed_state = $this->createMock( CompletedState::class );
		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( CompletedState::class, $this->mock_context )
			->willReturn( $mock_completed_state );

		$mock_cart = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'empty_cart' ] )
			->getMock();

		// Test that 'wc_reduce_stock_levels' is called.
		$this->mock_legacy_proxy
			->expects( $this->exactly( 2 ) )
			->method( 'call_function' )
			->withConsecutive( [ 'wc_reduce_stock_levels', 1 ], [ 'wc' ] )
			->willReturnCallback(
				function ( $arg ) use ( $mock_cart ) {
					if ( 'wc' === $arg ) {
							return (object) [
								'cart' => $mock_cart,
							];
					}
				}
			);

		// Test the 'empty_cart' is called.
		$mock_cart->expects( $this->once() )
			->method( 'empty_cart' );

		$this->sut->complete_processing();
	}
}
