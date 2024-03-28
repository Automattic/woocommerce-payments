<?php
/**
 * Trait file for WCPay\Core\Server\Request\Date_Parameters.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore;
use WC_Order;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;

/**
 * Tait for request date parameters.
 */
trait Date_Parameters {

	/**
	 * Set date after.
	 *
	 * @param string $date_after Date after.
	 *
	 * @return void
	 * @throws \WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception
	 */
	public function set_date_after( string $date_after ) {
		$this->validate_date( $date_after );
		$this->set_param( 'date_after', $date_after );
	}

	/**
	 * Set date before.
	 *
	 * @param string $date_before Date before.
	 *
	 * @return void
	 * @throws \WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception
	 */
	public function set_date_before( string $date_before ) {
		$this->validate_date( $date_before );
		$this->set_param( 'date_before', $date_before );
	}

	/**
	 * Set date between.
	 *
	 * @param array $date_between Date between.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_date_between( array $date_between ) {
		if ( ! empty( $date_between ) ) {
			foreach ( $date_between as $date ) {
				$this->validate_date( $date );
			}
			$this->set_param( 'date_between', $date_between );
		}
	}
}
