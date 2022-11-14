<?php
/**
 * Class WC_REST_UPE_Flag_Toggle_Controller
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Database_Cache;
use WCPay\Session_Rate_Limiter;
use WCPay\Settings;

/**
 * WC_REST_UPE_Flag_Toggle_Controller unit tests.
 */
class WC_REST_UPE_Flag_Toggle_Controller_Test extends WCPAY_UnitTestCase {

	/**
	 * Tested REST route.
	 */
	const ROUTE = '/wc/v3/payments/upe_flag_toggle';

	/**
	 * The system under test.
	 *
	 * @var WC_REST_UPE_Flag_Toggle_Controller
	 */
	private $controller;

	/**
	 * Gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_wcpay_account       = $this->createMock( WC_Payments_Account::class );
		$this->mock_db_cache      = $this->createMock( Database_Cache::class );
		$customer_service         = new WC_Payments_Customer_Service( $mock_api_client, $mock_wcpay_account, $this->mock_db_cache );
		$token_service            = new WC_Payments_Token_Service( $mock_api_client, $customer_service );
		$action_scheduler_service = new WC_Payments_Action_Scheduler_Service( $mock_api_client );
		$rate_limiter             = new Session_Rate_Limiter( 'wcpay_card_declined_registry', 5, 60 );
		$order_service            = new WC_Payments_Order_Service( $mock_api_client, $customer_service, $action_scheduler_service );

		$payments_settings = new Settings( $mock_wcpay_account );

		$this->gateway    = new WC_Payment_Gateway_WCPay(
			$mock_api_client,
			$mock_wcpay_account,
			$customer_service,
			$token_service,
			$action_scheduler_service,
			$rate_limiter,
			$order_service,
			$payments_settings
		);
		$this->controller = new WC_REST_UPE_Flag_Toggle_Controller( $this->gateway );
	}

	public function test_get_flag_request_returns_status_code_200() {
		$response = $this->controller->get_flag();

		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_set_flag_enabled_request_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'is_upe_enabled', true );

		$response = $this->controller->set_flag( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( '1', get_option( '_wcpay_feature_upe' ) );
	}

	public function test_set_flag_disabled_request_returns_status_code_200() {
		$this->gateway->update_option(
			'upe_enabled_payment_method_ids',
			[
				'card',
				'giropay',
			]
		);
		update_option( '_wcpay_feature_upe', '1' );
		$this->assertEquals( '1', get_option( '_wcpay_feature_upe' ) );

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'is_upe_enabled', false );

		$response = $this->controller->set_flag( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'disabled', get_option( '_wcpay_feature_upe', null ) );
		$this->assertEquals(
			[
				'card',
			],
			$this->gateway->get_option(
				'upe_enabled_payment_method_ids'
			)
		);
	}
}
