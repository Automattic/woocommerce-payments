<?php
namespace WCPay\Core\State_Machine;

abstract class State {
	public function get_id(): string {
		return static::class;
	}
}

abstract class Failed_State extends State {
	public function __construct( string $error_code, ?string $reason = null ) {
		$this->error_code = $error_code;
		$this->reason     = $reason;
	}
}

/**
 * Main business logics are embedded in method act().
 */
abstract class Internal_State extends State {
	abstract public function act( Entity_Payment &$entity, Input $input = null ): State;
}

/**
 * The only difference is that Async_State allows to terminate,
 * and only keep going when having more input.
 */
abstract class Async_State extends State {
	abstract public function act( Entity_Payment &$entity, Input $input = null ): State;
}

abstract class Final_State extends State {

}
