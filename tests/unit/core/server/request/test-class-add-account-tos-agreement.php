<?php
/**
 * Class Add_Account_Tos_Agreement_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Add_Account_Tos_Agreement;

/**
 * WCPay\Core\Server\Get_Intention_Test unit tests.
 */
class Add_Account_Tos_Agreement_Test extends WCPAY_UnitTestCase {

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;
	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_Http_Interface|MockObject
	 */
	private $mock_wc_payments_http_client;


	/**
	 * Set up the unit tests objects.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client              = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_wc_payments_http_client = $this->createMock( WC_Payments_Http_Interface::class );
	}

	public function test_add_account_tos_agreement_will_be_sent() {
		$request = new Add_Account_Tos_Agreement( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_source( 'test_source' );
		$admin_username = get_user_by( 'id', 1 )->user_login;
		$request->set_user_name( $admin_username );
		$this->assertInstanceOf( Add_Account_Tos_Agreement::class, $request );
		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( 'test_source', $params['source'] );
		$this->assertSame( $admin_username, $params['user_name'] );
		$this->assertSame( 'accounts/tos_agreements', $request->get_api() );
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertTrue( $request->should_use_user_token() );
	}

	public function test_add_account_tos_agreement_with_invalid_user_name_param_will_throw_exception() {
		$request = new Add_Account_Tos_Agreement( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_user_name( 'invalid.test.username' );
	}
}
