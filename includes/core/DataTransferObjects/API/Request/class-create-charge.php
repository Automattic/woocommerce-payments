<?php

namespace WCPay\Core\DataTransferObjects\API\Request;

use WCPay\Core\DataTransferObjects\Data_Transfer_Object;

final class Create_Charge extends Data_Transfer_Object
{
	/**
	 * @var int $amount Amount to charge.
	 */
	private $amount;

	/**
	 * @var string $source_id ID of the source to associate with charge.
	 */
	private $source_id;

	/**
	 * DTO constructor.
	 * @param int $amount Amount to charge.
	 * @param string $source_id ID of the source to associate with charge.
	 */
	public function __construct($amount, $source_id)
	{
		$this->amount = $amount;
		$this->source_id = $source_id;
	}

	/**
	 * @return int
	 */
	public function get_amount()
	{
		return $this->amount;
	}

	/**
	 * @return string
	 */
	public function get_source_id()
	{
		return $this->source_id;
	}

	/**
	 * @return array
	 */
	public function to_array()
	{
		return [
			'amount' => $this->amount,
			'source' => $this->source_id,
		];
	}


}
