<?php
/**
 * Class LegacyContainer
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\DependencyManagement\DelegateContainer;

use WC_Payments;
use WCPay\Vendor\Psr\Container\ContainerInterface;
use WCPay\Vendor\League\Container\Exception\ContainerException;

/**
 * WooPayments Legacy Container Delegate.
 *
 * This class is a proxy between `src` and `includes`, allowing
 * `includes` classes to be used as dependencies.
 */
class LegacyContainer implements ContainerInterface {
	/**
	 * Finds an entry of the container by its identifier and returns it.
	 *
	 * @template ID
	 * @param class-string<ID> $id Identifier of the entry to look for.
	 * @return ID
	 * @throws ContainerException In case the container cannot resolve the identifier.
	 *
	 * Psalm expects $id to be a string, based on ContainerInterface.
	 * @psalm-suppress MoreSpecificImplementedParamType
	 */
	public function get( $id ) {
		$method = $this->transform_class_to_method( $id );

		if ( ! $this->has( $id ) ) {
			throw new ContainerException( sprintf( 'Class (%s) is not being managed by the legacy container', $id ) );
		}

		return $this->$method();
	}

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 * Returns false otherwise.
	 *
	 * @param string $id Identifier of the entry to look for.
	 * @return bool
	 */
	public function has( $id ) {
		$method = $this->transform_class_to_method( $id );
		return method_exists( $this, $method );
	}

	/**
	 * Transforms the name of an existing class to a method name.
	 *
	 * @param string $class_name The name of a class from `includes`.
	 * @return string            Possibly the name of a private method of this class.
	 */
	private function transform_class_to_method( string $class_name ) {
		return 'get_' . str_replace( '\\', '_', strtolower( $class_name ) ) . '_instance';
	}

	/**
	 * Returns an instance of the mode class.
	 *
	 * @return \WCPay\Core\Mode
	 */
	private function get_wcpay_core_mode_instance() {
		return WC_Payments::mode();
	}

	/**
	 * Returns main CC gateway registered for WCPay.
	 *
	 * @return \WC_Payment_Gateway_WCPay
	 */
	private function get_wc_payment_gateway_wcpay_instance() {
		return WC_Payments::get_gateway();
	}

	/**
	 * Returns the WooPay_Tracker instance.
	 *
	 * @return \WCPay\WooPay_Tracker
	 */
	private function get_wcpay_woopay_tracker_instance() {
		return WC_Payments::woopay_tracker();
	}

	/**
	 * Returns the WC_Payments_Checkout instance.
	 *
	 * @return \WCPay\WC_Payments_Checkout
	 */
	private function get_wcpay_wc_payments_checkout_instance() {
		return WC_Payments::get_wc_payments_checkout();
	}

	/**
	 * Returns the Database_Cache instance.
	 *
	 * @return \WCPay\Database_Cache
	 */
	private function get_wcpay_database_cache_instance() {
		return WC_Payments::get_database_cache();
	}

	/**
	 * Returns the WC_Payments_Account instance.
	 *
	 * @return \WC_Payments_Account
	 */
	private function get_wc_payments_account_instance() {
		return WC_Payments::get_account_service();
	}

	/**
	 * Returns the WC_Payments_API_Client.
	 *
	 * @return \WC_Payments_API_Client
	 */
	private function get_wc_payments_api_client_instance() {
		return WC_Payments::get_payments_api_client();
	}

	/**
	 * Returns the WC_Payments_Localization_Service.
	 *
	 * @return \WC_Payments_Localization_Service
	 */
	private function get_wc_payments_localization_service_instance() {
		return WC_Payments::get_localization_service();
	}

	/**
	 * Returns the WC_Payments_Action_Scheduler_Service.
	 *
	 * @return \WC_Payments_Action_Scheduler_Service
	 */
	private function get_wc_payments_action_scheduler_service_instance() {
		return WC_Payments::get_action_scheduler_service();
	}

	/**
	 * Returns the WC_Payments_Fraud_Service instance.
	 *
	 * @return \WC_Payments_Fraud_Service
	 */
	private function get_wc_payments_fraud_service_instance() {
		return WC_Payments::get_fraud_service();
	}

	/**
	 * Returns the WC_Payments_Customer_Service instance.
	 *
	 * @return \WC_Payments_Customer_Service
	 */
	private function get_wc_payments_customer_service_instance() {
		return WC_Payments::get_customer_service();
	}

	/**
	 * Returns the WC_Payments_Order_Service instance.
	 *
	 * @return \WC_Payments_Order_Service
	 */
	private function get_wc_payments_order_service_instance() {
		return WC_Payments::get_order_service();
	}
}
