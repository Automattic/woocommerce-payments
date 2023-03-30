<?php
/**
 * Trait file for WCPay\Core\Server\Request\Request_Test_Mode_Only_For_Dev_Mode.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments;
use WC_Payments_API_Client;
use WC_Payments_Http_Interface;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;

/**
 * Trait for requests that we only send a test mode request if in dev mode.
 */
trait Use_Test_Mode_Only_When_Dev_Mode {
	/**
	 * Class constructor.
	 *
	 * @param  WC_Payments_API_Client     $api_client  Api client.
	 * @param  WC_Payments_Http_Interface $http_interface  Http interface.
	 *
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payments_Http_Interface $http_interface ) {
		parent::__construct( $api_client, $http_interface );
		$this->set_param( 'test_mode', WC_Payments::mode()->is_dev() );
	}
}
