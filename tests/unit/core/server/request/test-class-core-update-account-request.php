<?php
/**
 * Class Update_Account_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\Update_Account;

/**
 * WCPay\Core\Server\Update_Account_Test unit tests.
 */
class Update_Account_Test extends WCPAY_UnitTestCase {

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

	public function test_update_account_request_will_be_created() {
		$request = new Update_Account( $this->mock_api_client, $this->mock_wc_payments_http_client );

		$request->set_statement_descriptor( 'test statement_descriptor' );
		$request->set_business_name( 'test business_name' );
		$request->set_business_url( 'test business_url' );
		$request->set_business_support_address( 'test business_support_address' );
		$request->set_business_support_email( 'test business_support_email' );
		$request->set_business_support_phone( 'test business_support_phone' );
		$request->set_branding_logo( 'test branding_logo' );
		$request->set_branding_icon( 'test branding_icon' );
		$request->set_branding_primary_color( 'test branding_primary_color' );
		$request->set_branding_secondary_color( 'test branding_secondary_color' );
		$request->set_deposit_schedule_interval( 'test deposit_schedule_interval' );
		$request->set_deposit_schedule_weekly_anchor( 'test deposit_schedule_weekly_anchor' );
		$request->set_deposit_schedule_monthly_anchor( 'test deposit_schedule_monthly_anchor' );
		$request->set_locale( 'test locale' );

		$this->assertInstanceOf( Update_Account::class, $request );

		// Asset params.
		$params = $request->get_params();
		$this->assertSame( 'test statement_descriptor', $params['statement_descriptor'] );
		$this->assertSame( 'test business_name', $params['business_name'] );
		$this->assertSame( 'test business_url', $params['business_url'] );
		$this->assertSame( 'test business_support_address', $params['business_support_address'] );
		$this->assertSame( 'test business_support_email', $params['business_support_email'] );
		$this->assertSame( 'test business_support_phone', $params['business_support_phone'] );
		$this->assertSame( 'test branding_logo', $params['branding_logo'] );
		$this->assertSame( 'test branding_icon', $params['branding_icon'] );
		$this->assertSame( 'test branding_primary_color', $params['branding_primary_color'] );
		$this->assertSame( 'test branding_secondary_color', $params['branding_secondary_color'] );
		$this->assertSame( 'test deposit_schedule_interval', $params['deposit_schedule_interval'] );
		$this->assertSame( 'test deposit_schedule_weekly_anchor', $params['deposit_schedule_weekly_anchor'] );
		$this->assertSame( 'test deposit_schedule_monthly_anchor', $params['deposit_schedule_monthly_anchor'] );
		$this->assertSame( 'test locale', $params['locale'] );

		// Assert method and endpoint.
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertSame( 'accounts', $request->get_api() );
	}
}
