<?php
/**
 * Class WC_Payments_Checkout
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use WC_Payments;
use WC_Payments_Account;
use WC_Payments_Customer_Service;
use WC_Payments_Fraud_Service;
use WC_Payments_Utils;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\WooPay\WooPay_Utilities;


/**
 * WC_Payments_Checkout
 */
class WC_Payments_Checkout {
	/**
	 * WooPay Utilities.
	 *
	 * @var WooPay_Utilities
	 */
	protected $woopay_util;

	/**
	 * WC Payments Account.
	 *
	 * @var WC_Payments_Account
	 */
	protected $account;

	/**
	 * WC Payments Customer Service
	 *
	 * @var WC_Payments_Customer_Service
	 */
	protected $customer_service;

	/**
	 * WC_Payments_Fraud_Service instance to get information about fraud services.
	 *
	 * @var WC_Payments_Fraud_Service
	 */
	protected $fraud_service;

	/**
	 * Construct.
	 *
	 * @param WooPay_Utilities             $woopay_util            WooPay Utilities.
	 * @param WC_Payments_Account          $account                WC Payments Account.
	 * @param WC_Payments_Customer_Service $customer_service       WC Payments Customer Service.
	 * @param WC_Payments_Fraud_Service    $fraud_service          Fraud service instance.
	 */
	public function __construct(
		WooPay_Utilities $woopay_util,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Fraud_Service $fraud_service
	) {
		$this->woopay_util      = $woopay_util;
		$this->account          = $account;
		$this->customer_service = $customer_service;
		$this->fraud_service    = $fraud_service;
	}
}
