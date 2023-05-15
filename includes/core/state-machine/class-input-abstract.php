<?php
namespace WCPay\Core\State_Machine;

abstract class Input {
	/**
	 * @var array
	 */
	private $data = [];

	public function set( string $key, $value) {
		$this->data[$key] = $value;
	}

	public function get(string $key) {
		return $this->data[$key] ?? null;
	}

	public function exist( string $key ): bool {
		return isset( $this->data[$key] );
	}
}
