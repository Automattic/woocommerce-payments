<?php

namespace WCPay\Core\DataTransferObjects\API;

use WCPay\Core\DataTransferObjects\Data_Transfer_Object;

final class Order extends Data_Transfer_Object
{
	/**
	 * @var string $order_number Order number.
	 */
	private $order_number;

	/**
	 * @var string $edit_order_url Edit order url.
	 */
	private $edit_order_url;

	/**
	 * @var string $customer_url Customer url.
	 */
	private $customer_url;

	/**
	 * @var Subscripton[] $subscriptions Order subscriptions.
	 */
	private $subscriptions;

	/**
	 * @param string $order_number Order number.
	 * @param string $edit_order_url Edit order url.
	 * @param string $customer_url Customer url.
	 */
	public function __construct($order_number, $edit_order_url, $customer_url, $subscriptions)
	{
		$this->order_number = $order_number;
		$this->edit_order_url = $edit_order_url;
		$this->customer_url = $customer_url;
		$this->subscriptions = $subscriptions;
	}


	/**
	 * Get order number.
	 *
	 * @return string
	 */
	public function get_order_number()
	{
		return $this->order_number;
	}

	/**
	 * Get edit order url.
	 *
	 * @return string
	 */
	public function get_edit_order_url()
	{
		return $this->edit_order_url;
	}

	/**
	 * Get customer url.
	 *
	 * @return string
	 */
	public function get_customer_url()
	{
		return $this->customer_url;
	}


	/**
	 * Get subscriptions.
	 * @return Subscripton[]
	 */
	public function get_subscriptions() {
		return $this->subscriptions;
	}
}
