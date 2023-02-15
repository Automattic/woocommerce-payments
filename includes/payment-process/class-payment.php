<?php
namespace WCPay\Payment_Process;

use WCPay\Payment_Process\Storage\Payment_Storage;

abstract class Payment {
	/** @var Payment_Storage */
	protected $payment_storage;

	/** @var string */
	protected $id;

	/** @var Payment_Method */
	private $payment_method;

	public function __construct( Payment_Storage $storage ) {
		$this->payment_storage = $storage;
	}

	public function load_data( $data ) {
		$this->payment_method = $data['payment_method'];
	}

	protected function get_data() {
		return [
			'payment_method' => $this->payment_method,
		];
	}

	abstract public function save();
}
