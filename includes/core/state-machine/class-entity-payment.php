<?php
namespace WCPay\Core\State_Machine;


class Entity_Payment implements Entity {
	/**
	 * @var array
	 */
	private $data = [];

	/**
	 * @var array
	 */
	private $diff_data = [];

	/**
	 * @var array
	 */
	private $revisions = [];

	/** @var null | string Class name of State */
	private $current_state = null;

	/**
	 * @var int
	 */
	private $order_id;

	public function __construct( int $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, \WC_Order::class ) ) {
			throw new \Exception( 'Valid order ID is required' );
		}
		$this->data['order_id'] = $order_id;
	}

	public function get_order_id(): int {
		return $this->order_id;
	}

	public function get_current_state(): ?State {
		return null === $this->current_state
			? null
			: new $this->current_state();
	}

	public function get_revisions(): ?array {
		return $this->revisions;
	}

	public function log( State $previous_state, State $current_state, Input $input, State_Machine_Abstract $state_machine, int $timestamp = null ) {
		$this->revisions[] = [
			'timestamp' => $timestamp ?? time(),
			'input' => $input,
			'previous_state' => $previous_state->get_id(),
			'current_state' => $current_state->get_id(),
			'state_machine' => $state_machine->get_id(),
			'diff_data' => $this->diff_data,
		];

		$this->current_state = $current_state->get_id();
	}

	public function set( string $key, $value) {
		$this->data[$key] = $value;
		$this->diff_data[$key] = $value;
	}

	public function get(string $key) {
		return $this->data[$key] ?? null;
	}

	public function exist( string $key ): bool {
		return isset( $this->data[$key] );
	}
}
