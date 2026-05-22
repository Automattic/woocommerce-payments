<?php
/**
 * Class file for WCPay\Core\Server\Request\Get_Reporting_Balance_Summary.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments_API_Client;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;

/**
 * Request class for retrieving Balance report summary data.
 */
class Get_Reporting_Balance_Summary extends Request {
	/**
	 * Required request parameters.
	 *
	 * @var string[]
	 */
	const REQUIRED_PARAMS = [
		'date_start',
		'date_end',
		'currency',
	];

	/**
	 * Specifies the WordPress hook name that will be triggered upon calling the send() method.
	 *
	 * @var string
	 */
	protected $hook = 'wcpay_get_reports_balance_summary_request';

	/**
	 * Gets the API URI.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::REPORTING_API . '/balance_summary';
	}

	/**
	 * Gets the request HTTP method.
	 *
	 * @return string
	 */
	public function get_method(): string {
		return 'GET';
	}

	/**
	 * Sets the report period start date.
	 *
	 * @param string $date_start Report period start date.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception When the date is invalid.
	 */
	public function set_date_start( string $date_start ) {
		$this->validate_rest_date_time( $date_start );
		$this->set_param( 'date_start', $date_start );
	}

	/**
	 * Sets the report period end date.
	 *
	 * @param string $date_end Report period end date.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception When the date is invalid.
	 */
	public function set_date_end( string $date_end ) {
		$this->validate_rest_date_time( $date_end );
		$this->set_param( 'date_end', $date_end );
	}

	/**
	 * Sets the report currency.
	 *
	 * @param string $currency Report currency.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception When the currency code is invalid.
	 */
	public function set_currency( string $currency ) {
		$currency = strtolower( $currency );
		if ( 1 !== preg_match( '/^[a-z]{3}$/', $currency ) ) {
			throw new Invalid_Request_Parameter_Exception(
				esc_html(
					sprintf(
						// Translators: %s is a currency code.
						__( '%s is not a valid currency code.', 'woocommerce-payments' ),
						$currency
					)
				),
				'wcpay_core_invalid_request_parameter_currency_code'
			);
		}

		$this->set_param( 'currency', $currency );
	}

	/**
	 * Formats the response as a raw array.
	 *
	 * @param mixed $response The response from `WC_Payments_API_Client::request`.
	 *
	 * @return mixed
	 */
	public function format_response( $response ) {
		return $response;
	}

	/**
	 * Validates a REST date-time value.
	 *
	 * @param string $date_time Date-time value.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception When the date-time value is invalid.
	 */
	private function validate_rest_date_time( string $date_time ) {
		if ( false !== rest_parse_date( $date_time ) ) {
			return;
		}

		throw new Invalid_Request_Parameter_Exception(
			esc_html(
				sprintf(
					// Translators: %s is a date-time string.
					__( '%s is not a valid date-time value.', 'woocommerce-payments' ),
					$date_time
				)
			),
			'wcpay_core_invalid_request_parameter_invalid_date_time'
		);
	}
}
