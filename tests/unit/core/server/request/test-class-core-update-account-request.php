<?php
/**
 * Class Update_Account_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Update_Account;

/**
 * WCPay\Core\Server\Update_Account_Test unit tests.
 */
class Update_Account_Test extends WCPAY_UnitTestCase {
	public function test_update_account_request_will_be_created_with_from_account_settings() {
		// Create request.
		$account_setting = [
			'statement_descriptor'            => 'test statement_descriptor',
			'business_name'                   => 'test business_name',
			'business_url'                    => 'test business_url',
			'business_support_address'        => 'test business_support_address',
			'business_support_email'          => 'test business_support_email',
			'business_support_phone'          => 'test business_support_phone',
			'branding_logo'                   => 'test branding_logo',
			'branding_icon'                   => 'test branding_icon',
			'branding_primary_color'          => 'test branding_primary_color',
			'branding_secondary_color'        => 'test branding_secondary_color',
			'deposit_schedule_interval'       => 'test deposit_schedule_interval',
			'deposit_schedule_weekly_anchor'  => 'test deposit_schedule_weekly_anchor',
			'deposit_schedule_monthly_anchor' => 'test deposit_schedule_monthly_anchor',
			'locale'                          => 'test locale',
		];

		$request = Update_Account::from_account_settings( $account_setting );

		// Assert request class.
		$this->assertInstanceOf( Update_Account::class, $request );

		// Assert params.
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

		// Assert using user token.
		$this->assertTrue( $request->should_use_user_token() );
	}

	public function test_from_account_settings_with_empty_input_will_throw_exception() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$this->expectExceptionMessage( 'No account settings provided' );

		Update_Account::from_account_settings( [] );
	}

	public function test_from_account_settings_with_non_existing_param_continue_to_run() {
		$non_existing_param = 'non_existing_param';

		// Do not throw any error at this point as the code continues to run even with a non-existing param.
		$request = Update_Account::from_account_settings( [ $non_existing_param => 'test_value' ] );

		// Check this non-existing param will throw exception.
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$this->expectExceptionMessage( "The passed key $non_existing_param does not exist in Request class" );
		$request->get_param( $non_existing_param );
	}
}
