<?php
namespace WCPay\Core\State_Machine;

abstract class Input {
	/** @var ?array */
	private $data = null;

	protected function set(string $key, $value) {
		$this->data[$key] = $value;
	}

	protected function get(string $key) {
		return $this->data[$key] ?? null;
	}
}
