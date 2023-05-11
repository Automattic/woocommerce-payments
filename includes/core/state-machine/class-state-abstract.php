<?php
namespace WCPay\Core\State_Machine;

abstract class State {
	public function get_id(): string {
		return static::class;
	}
}

abstract class Final_State extends State {

}

abstract class Failed_State extends State {
	public function __construct( string $error_code, ?string $reason = null ) {
		$this->error_code = $error_code;
		$this->reason     = $reason;
	}
}

class General_Failed_State extends Failed_State {
}


abstract class Happy_State extends State {
	abstract public function act( Entity_Payment &$entity, Input $input = null ): State;
}

abstract class Async_State extends State {
	// The only difference is that Async_State allows to terminate,
	// and only keep going when having more input.
	abstract public function act( Entity_Payment &$entity, Input $input = null ): State;
}
