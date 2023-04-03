<?php
/**
 * Class file for WCPay\Core\Server\Request\Update_Account.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for updating account.
 */
class Update_Account extends Request {
	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::ACCOUNTS_API;
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'POST';
	}

	/**
	 * If true, the request will be signed with the user token rather than blog token.
	 *
	 * @return bool
	 */
	public function should_use_user_token(): bool {
		return true;
	}

	/**
	 * Sets the account statement descriptor.
	 *
	 * @param  string $statement_descriptor Statement descriptor.
	 *
	 * @return void
	 */
	public function set_statement_descriptor( string $statement_descriptor ) {
		$this->set_param( 'statement_descriptor', $statement_descriptor );
	}

	/**
	 * Sets the account business name.
	 *
	 * @param  string $business_name Business name.
	 *
	 * @return void
	 */
	public function set_business_name( string $business_name ) {
		$this->set_param( 'business_name', $business_name );
	}

	/**
	 * Sets the account business url.
	 *
	 * @param  string $business_url Business url.
	 *
	 * @return void
	 */
	public function set_business_url( string $business_url ) {
		$this->set_param( 'business_url', $business_url );
	}

	/**
	 * Sets the account business support address.
	 *
	 * @param  string $business_support_address Business support address.
	 *
	 * @return void
	 */
	public function set_business_support_address( string $business_support_address ) {
		$this->set_param( 'business_support_address', $business_support_address );
	}

	/**
	 * Sets the account business support email.
	 *
	 * @param  string $business_support_email Business support email.
	 *
	 * @return void
	 */
	public function set_business_support_email( string $business_support_email ) {
		$this->set_param( 'business_support_email', $business_support_email );
	}

	/**
	 * Sets the account business support phone.
	 *
	 * @param  string $business_support_phone Business support phone.
	 *
	 * @return void
	 */
	public function set_business_support_phone( string $business_support_phone ) {
		$this->set_param( 'business_support_phone', $business_support_phone );
	}

	/**
	 * Sets the account branding logo.
	 *
	 * @param  string $branding_logo Branding logo.
	 *
	 * @return void
	 */
	public function set_branding_logo( string $branding_logo ) {
		$this->set_param( 'branding_logo', $branding_logo );
	}

	/**
	 * Sets the account branding icon.
	 *
	 * @param  string $branding_icon Branding icon.
	 *
	 * @return void
	 */
	public function set_branding_icon( string $branding_icon ) {
		$this->set_param( 'branding_icon', $branding_icon );
	}

	/**
	 * Sets the account branding primary color.
	 *
	 * @param  string $branding_primary_color Branding primary color.
	 *
	 * @return void
	 */
	public function set_branding_primary_color( string $branding_primary_color ) {
		$this->set_param( 'branding_primary_color', $branding_primary_color );
	}

	/**
	 * Sets the account branding secondary color.
	 *
	 * @param  string $branding_secondary_color Branding secondary color.
	 *
	 * @return void
	 */
	public function set_branding_secondary_color( string $branding_secondary_color ) {
		$this->set_param( 'branding_secondary_color', $branding_secondary_color );
	}

	/**
	 * Sets the deposit schedule interval.
	 *
	 * @param  string $deposit_schedule_interval Deposit schedule interval.
	 *
	 * @return void
	 */
	public function set_deposit_schedule_interval( string $deposit_schedule_interval ) {
		$this->set_param( 'deposit_schedule_interval', $deposit_schedule_interval );
	}

	/**
	 * Sets the deposit schedule weekly anchor.
	 *
	 * @param  string $deposit_schedule_weekly_anchor Deposit schedule weekly anchor.
	 *
	 * @return void
	 */
	public function set_deposit_schedule_weekly_anchor( string $deposit_schedule_weekly_anchor ) {
		$this->set_param( 'deposit_schedule_weekly_anchor', $deposit_schedule_weekly_anchor );
	}

	/**
	 * Sets the deposit schedule monthly anchor.
	 *
	 * @param  string $deposit_schedule_monthly_anchor Deposit schedule monthly anchor.
	 *
	 * @return void
	 */
	public function set_deposit_schedule_monthly_anchor( string $deposit_schedule_monthly_anchor ) {
		$this->set_param( 'deposit_schedule_monthly_anchor', $deposit_schedule_monthly_anchor );
	}

	/**
	 * Sets the account locale.
	 *
	 * @param  string $locale Account locale.
	 *
	 * @return void
	 */
	public function set_locale( string $locale ) {
		$this->set_param( 'locale', $locale );
	}
}
