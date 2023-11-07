<?php
/**
 * Class PaymentProcessingServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use Exception;
use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WC_Helper_Intention;
use WC_Payment_Gateway_WCPay;
use WC_Payments_API_Abstract_Intention;
use WC_Payments_API_Setup_Intention;
use WCPay\Constants\Intent_Status;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\PaymentRequest;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Payment\State\InitialState;
use WCPay\Core\Mode;
use WCPay\Internal\Logger;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Payment\State\StateFactory;
use WCPay\Internal\Service\PaymentProcessingService;
use WCPay\Internal\Service\PaymentContextLoggerService;

/**
 * Payment processing service unit tests.
 */
class PaymentProcessingServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var PaymentProcessingService|MockObject
	 */
	private $sut;

	/**
	 * @var StateFactory|MockObject
	 */
	private $mock_state_factory;

	/**
	 * @var LegacyProxy|MockObject
	 */
	private $mock_legacy_proxy;

	/**
	 * @var PaymentContextLoggerService|MockObject
	 */
	private $mock_context_logger;

	/**
	 * @var Logger|MockObject
	 */
	private $mock_logger;

	/**
	 * @var Mode|MockObject
	 */
	private $mock_mode;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_state_factory  = $this->createMock( StateFactory::class );
		$this->mock_legacy_proxy   = $this->createMock( LegacyProxy::class );
		$this->mock_context_logger = $this->createMock( PaymentContextLoggerService::class );
		$this->mock_logger         = $this->createMock( Logger::class );
		$this->mock_mode           = $this->createMock( Mode::class );

		$this->sut = $this->getMockBuilder( PaymentProcessingService::class )
			->setConstructorArgs(
				[
					$this->mock_state_factory,
					$this->mock_legacy_proxy,
					$this->mock_context_logger,
					$this->mock_logger,
					$this->mock_mode,
				]
			)
			->onlyMethods( [ 'create_payment_context' ] )
			->getMock();
	}

	/**
	 * Test the basic happy path of processing a payment.
	 */
	public function test_process_payment_happy_path() {
		// Prepare all required mocks.
		$mock_initial_state   = $this->createMock( InitialState::class );
		$mock_completed_state = $this->createMock( CompletedState::class );

		$sut = new PaymentProcessingService( $this->mock_state_factory, $this->mock_legacy_proxy, $this->mock_context_logger, $this->mock_logger, $this->mock_mode );

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( InitialState::class, $this->isInstanceOf( PaymentContext::class ) )
			->willReturn( $mock_initial_state );

		$mock_initial_state->expects( $this->once() )
			->method( 'start_processing' )
			->with( $this->isInstanceOf( PaymentRequest::class ) )
			->willReturn( $mock_completed_state );

		$this->mock_context_logger->expects( $this->once() )
			->method( 'log_changes' )
			->with( $this->isInstanceOf( PaymentContext::class ) );

		$result = $sut->process_payment( 1 );
		$this->assertSame( $mock_completed_state, $result );
	}

	/**
	 * Test the basic happy path of processing a payment.
	 */
	public function test_process_payment_happy_path_without_mock_builder() {
		$sut = new PaymentProcessingService( $this->mock_state_factory, $this->mock_legacy_proxy, $this->mock_context_logger, $this->mock_logger, $this->mock_mode );

		$mock_initial_state   = $this->createMock( InitialState::class );
		$mock_completed_state = $this->createMock( CompletedState::class );

		// Prepare a payment method for the context.
		$_POST['payment_method']       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$_POST['wcpay-payment-method'] = 'pi_XYZ';

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( InitialState::class, $this->isInstanceOf( PaymentContext::class ) )
			->willReturn( $mock_initial_state );

		$mock_initial_state->expects( $this->once() )
			->method( 'start_processing' )
			->with( $this->isInstanceOf( PaymentRequest::class ) )
			->willReturn( $mock_completed_state );

		$result = $sut->process_payment( 1 );
		$this->assertSame( $mock_completed_state, $result );
	}

	/**
	 * Test the process payment when mode not initialized.
	 */
	public function test_process_payment_mode_throws_exception() {
		$this->mock_mode
			->expects( $this->once() )
			->method( 'is_test' )
			->willThrowException( new Exception( 'Could not initialize' ) );
		$this->mock_logger
			->expects( $this->once() )
			->method( 'error' );

		$sut                  = new PaymentProcessingService( $this->mock_state_factory, $this->mock_legacy_proxy, $this->mock_context_logger, $this->mock_logger, $this->mock_mode );
		
		$mock_initial_state   = $this->createMock( InitialState::class );
		$mock_completed_state = $this->createMock( CompletedState::class );
		
		// Prepare a payment method for the context.
		$_POST['payment_method']       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$_POST['wcpay-payment-method'] = 'pi_XYZ';

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( InitialState::class, $this->isInstanceOf( PaymentContext::class ) )
			->willReturn( $mock_initial_state );

		$mock_initial_state->expects( $this->once() )
			->method( 'start_processing' )
			->with( $this->isInstanceOf( PaymentRequest::class ) )
			->willReturn( $mock_completed_state );

		// process completes as mode is used only for logging and meta.
		$this->assertSame( $mock_completed_state, $sut->process_payment( 1 ) );
	}

	public function test_get_authentication_redirect_url_will_return_url_from_payment_intent() {
		$sut = new PaymentProcessingService( $this->mock_state_factory, $this->mock_legacy_proxy, $this->mock_context_logger, $this->mock_logger, $this->mock_mode );

		$url         = 'localhost';
		$intent_data = [
			'next_action' => [
				'type'            => 'redirect_to_url',
				'redirect_to_url' => [
					'url' => $url,
				],
			],
			'status'      => Intent_Status::REQUIRES_CAPTURE,
		];
		$intent      = WC_Helper_Intention::create_intention( $intent_data );

		$this->mock_legacy_proxy->expects( $this->never() )
			->method( 'call_static' );

		$this->mock_legacy_proxy->expects( $this->never() )
			->method( 'call_function' );

		$result = $sut->get_authentication_redirect_url( $intent, 1 );
		$this->assertSame( $url, $result );

	}

	/**
	 * Test URL will be returned with encrypted secret key
	 *
	 * @param WC_Payments_API_Abstract_Intention $intent
	 *
	 * @dataProvider intent_provider_for_encrypted_key_urls
	 * @return void
	 */
	public function test_redirect_url_returns_url_with_encrypted_secret_key( $intent ) {
		$secret = 'encrypted_secret'; // Dummy text to avoid calling crypt function.
		$nonce  = 'nonce'; // Return of nonce function when called from legacy proxy.
		$order  = 1; // Order id.
		$prefix = $intent instanceof WC_Payments_API_Setup_Intention ? 'si' : 'pi';

		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_static' )
			->with( \WC_Payments_Features::class, 'is_client_secret_encryption_enabled' )
			->willReturn( true );

		$this->mock_legacy_proxy->expects( $this->exactly( 2 ) )
			->method( 'call_function' )
			->withConsecutive(
				[
					'openssl_encrypt',
					$intent->get_client_secret(),
					'aes-128-cbc',
					substr( $intent->get_customer_id(), 5 ),
					0,
					str_repeat( 'WC', 8 ),
				],
				[ 'wp_create_nonce', 'wcpay_update_order_status_nonce' ]
			)
			->willReturnOnConsecutiveCalls( $secret, $nonce );

		$result = $this->sut->get_authentication_redirect_url( $intent, $order );
		$this->assertSame( "#wcpay-confirm-$prefix:$order:$secret:$nonce", $result );
	}

	public function test_redirect_url_returns_url_with_non_encrypted_client_secret_when_encryption_disabled() {
		$intent = WC_Helper_Intention::create_setup_intention();
		$nonce  = 'nonce'; // Return of nonce function when called from legacy proxy.
		$order  = 1; // Order id.

		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_static' )
			->with( \WC_Payments_Features::class, 'is_client_secret_encryption_enabled' )
			->willReturn( false ); // Just to test when encryption is disabled.

		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'wp_create_nonce', 'wcpay_update_order_status_nonce' )
			->willReturn( $nonce );

		$result = $this->sut->get_authentication_redirect_url( $intent, $order );
		$this->assertSame( "#wcpay-confirm-si:$order:" . $intent->get_client_secret() . ":$nonce", $result );
	}

	/**
	 * Data provider for encrypted key url test.
	 *
	 * @return array[]
	 */
	public function intent_provider_for_encrypted_key_urls() {
		return [
			[ WC_Helper_Intention::create_intention() ],
			[ WC_Helper_Intention::create_setup_intention() ],
		];
	}
}
