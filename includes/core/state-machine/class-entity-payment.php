<?php
namespace WCPay\Core\State_Machine;
class Entity_Payment {
	private $data = [];
	private $diff_data = [];

	const RESERVED_KEYS = [
		'revision',
		'current_state',
		'order_id'
	];

	protected function set_data( string $key, $value) {
		if ( in_array( $key, self::RESERVED_KEYS ) ) {
			throw new \Exception( 'Please use another key. Input key is reserved: ' . $key );
		}
		$this->data[$key] = $value;
		$this->diff_data[$key] = $value;
	}

	protected function get_data($key) {
		return $this->data[$key] ?? null;
	}

	public function get_current_state(): ?string {
		// TODO: return the State object. Maybe through a State Factory.
		return $this->get_data('current_state');
	}

	public function get_revision(): ?array {
		return $this->get_data('revision');
	}

	public function log( State_Interface $previous_state, State_Interface $current_state, Input $input, State_Machine_Abstract $state_machine, int $timestamp = null ) {
		$this->data['revision'][] = [
			'timestamp' => $timestamp ?? time(),
			'input' => $input,
			'previous_state' => $previous_state->get_id(),
			'current_state' => $current_state->get_id(),
			'state_machine' => $state_machine->get_id(),
			'diff_data' => $this->diff_data,
		];

		$this->data['current_state'] = $current_state->get_id();
	}
}
