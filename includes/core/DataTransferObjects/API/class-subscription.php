<?php

namespace WCPay\Core\DataTransferObjects\API;

use WCPay\Core\DataTransferObjects\Data_Transfer_Object;

final class Subscription extends Data_Transfer_Object
{
	/**
	 * @var string $order_number Order number.
	 */
	private $order_number;

	/**
	 * @var string $edit_order_url Edit order url.
	 */
	private $edit_order_url;

	public function __construct($order_number, $edit_order_url)
	{
		$this->order_number = $order_number;
		$this->edit_order_url = $edit_order_url;
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

}
