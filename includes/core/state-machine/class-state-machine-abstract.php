<?php

namespace WCPay\Core\State_Machine;
abstract class State_Machine_Abstract {
	/**
	 *  Transit configuration, from which state to which state.
	 *
	 * @var array
	 */
	protected $config = [];

	/** @var null | Input */
	private $input = null;

	/**
	 * @var Internal_State | Async_State
	 */
	private $initial_state;

	/** @var Entity_Payment */
	private $entity;

	/**
	 * @var Entity_Storage_Payment
	 */
	private $storage;

	public function __construct( Entity_Storage_Payment $storage ) {
		$this->storage       = $storage;
	}

	public function get_id(): string {
		return self::class;
	}

	public function set_input( Input $input ): State_Machine_Abstract {
		$this->input = $input;
		return $this;
	}

	public function set_entity( Entity_Payment $entity ): State_Machine_Abstract {
		$this->entity = $entity;
		return $this;
	}

	public function set_initial_state( State $initial_state ): State_Machine_Abstract {
		$this->initial_state = $initial_state;
		return $this;
	}


	public function progress(): Entity_Payment {
		if ( count( $this->config ) === 0 ) {
			throw new \Exception( 'Transit configuration is not set' );
		}

		if ( ! $this->entity ) {
			throw new \Exception( 'Entity not set' );
		}

		$current_state = $this->initial_state ?? $this->entity->get_current_state();

		if ( ! $current_state ) {
			throw new \Exception( 'Initial state is not set, or there is no current state' );
		}

		do {
			$next_state = $current_state->act( $this->entity, $this->input);

			if( ! $this->is_valid_next_state($current_state, $next_state) ) {
				throw new \Exception( 'Transition does not exist from state: ' . $current_state->get_id() . ' to ' . $next_state->get_id() );
			}

			// Log the transition.
			$this->entity->log( $current_state, $next_state, $this->input, $this );
			$current_state = $next_state;

		} while ( is_a( $current_state, Internal_State::class ) );

		$this->storage->save( $this->entity );

		return $this->entity;
	}

	protected function is_valid_next_state( State $current_state, State $next_state): bool {
		return in_array( $next_state->get_id(), $this->config[ $current_state->get_id() ] ) ;
	}
}
