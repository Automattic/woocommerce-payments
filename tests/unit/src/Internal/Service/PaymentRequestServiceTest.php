<?php
/**
 * Class PaymentRequestServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use WC_Payments_API_Payment_Intention;
use WCPAY_UnitTestCase;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\PaymentMethod\NewPaymentMethod;
use WCPay\Internal\Service\PaymentRequestService;

/**
 * Level3 data service unit tests.
 */
class PaymentRequestServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var PaymentRequestService
	 */
	private $sut;

	/**
	 * Set up the tests.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->sut = new PaymentRequestService();
	}

	public function provider_create_intent() {
		return [
			'With fingerprint'    => [ 'fingerprint' ],
			'Without fingerprint' => [ null ],
		];
	}

	/**
	 * Tests the method, which creates and confirms intents.
	 *
	 * @dataProvider provider_create_intent
	 */
	public function test_create_intent( $fingerprint ) {
		$context_data = [
			'get_amount'                   => 123,
			'get_currency'                 => 'usd',
			'get_payment_method'           => new NewPaymentMethod( 'pm_XYZ' ),
			'get_customer_id'              => 'cus_XYZ',
			'should_capture_automatically' => false,
			'get_metadata'                 => [ 'metadata' ],
			'get_level3_data'              => [ 'level3data' ],
			'get_cvc_confirmation'         => 'confirmation',
			'get_fingerprint'              => $fingerprint,
		];

		$request_data = [
			'set_amount'           => 123,
			'set_currency_code'    => 'usd',
			'set_payment_method'   => 'pm_XYZ',
			'set_customer'         => 'cus_XYZ',
			'set_capture_method'   => true, // By default, the automatic capture is set to false.
			'set_metadata'         => [ 'metadata' ],
			'set_level3'           => [ 'level3data' ],
			'set_payment_methods'  => [ 'card' ],
			'set_cvc_confirmation' => 'confirmation',
			'set_fingerprint'      => $fingerprint ?? '',
		];

		$context = $this->createMock( PaymentContext::class );
		$intent  = $this->createMock( WC_Payments_API_Payment_Intention::class );
		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		foreach ( $context_data as $method => $value ) {
			$context->expects( $this->once() )->method( $method )->willReturn( $value );
		}

		foreach ( $request_data as $method => $value ) {
			$request->expects( $this->once() )->method( $method )->with( $value );
		}
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$result = $this->sut->create_intent( $context );
		$this->assertSame( $intent, $result );
	}
}
