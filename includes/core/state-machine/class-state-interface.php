<?php
namespace WCPay\Core\State_Machine;

interface State_Interface {
	public function get_id(): string;
}

abstract class Final_State implements State_Interface {

}

abstract class Failed_State implements State_Interface {
	public function __construct( string $error_code, ?string $reason = null ) {
		$this->error_code = $error_code;
		$this->reason     = $reason;
	}
}

class General_Failed_State extends Failed_State {
	public function get_id(): string {
		return 'failed_state';
	}
}


abstract class Happy_State implements State_Interface {
	abstract public function act( Entity_Payment &$entity, Input $input = null ): State_Interface;
}

abstract class Async_State implements State_Interface {
	// The only difference is that Async_State allows to terminate,
	// and only keep going when having more input.
	abstract public function act( Entity_Payment &$entity, Input $input = null ): State_Interface;
}
