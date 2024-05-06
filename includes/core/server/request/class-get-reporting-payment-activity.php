<?php
/**
 * Class file for WCPay\Core\Server\Request\Get_Payment_Activity.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for getting intents.
 */
class Get_Reporting_Payment_Activity extends Request {

	const REQUIRED_PARAMS = [
		'date_start',
		'date_end',
		'timezone',
	];

	/**
	 * Specifies the WordPress hook name that will be triggered upon calling the send() method.
	 *
	 * @var string
	 */
	protected $hook = 'wcpay_get_payment_activity';

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::REPORTING_API;
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'GET';
	}

	/**
	 * Sets the start date for the payment activity data.
	 *
	 * @param string $date_start The start date in the format 'YYYY-MM-DDT00:00:00'.
	 * @return void
	 *
	 * @throws Invalid_Request_Parameter_Exception Exception if the date is not in valid format.
	 */
	public function set_date_start( string $date_start ) {
		$this->validate_date( $date_start, 'Y-m-d\TH:i:s' );
		$this->set_param( 'date_start', $date_start );
	}

	/**
	 * Sets the end date for the payment activity data.
	 *
	 * @param string $date_end The end date in the format 'YYYY-MM-DDT00:00:00'.
	 * @return void
	 *
	 * @throws Invalid_Request_Parameter_Exception Exception if the date is not in valid format.
	 */
	public function set_date_end( string $date_end ) {
		$this->validate_date( $date_end, 'Y-m-d\TH:i:s' );
		$this->set_param( 'date_end', $date_end );
	}

	/**
	 * Sets the timezone for the reporting data.
	 *
	 * @param string $timezone The timezone to set.
	 * @return void
	 *
	 * @throws Invalid_Request_Parameter_Exception Exception if the timezone is not in valid format.
	 */
	public function set_timezone( string $timezone ) {
		try {
			new \DateTimeZone( $timezone );
		} catch ( \Exception $e ) {
			throw new Invalid_Request_Parameter_Exception(
				esc_html(
					sprintf(
						// Translators: %s is a provided timezone.
						__( '%s is not a valid timezone.', 'woocommerce-payments' ),
						$timezone,
					)
				),
				'wcpay_core_invalid_request_parameter_invalid_timezone'
			);
		}
		$this->set_param( 'timezone', $timezone );
	}
}
