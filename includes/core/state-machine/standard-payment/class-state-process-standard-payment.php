<?php

namespace WCPay\Core\State_Machine;

class Process_Standard_Payment_State extends Happy_State {
	public function get_id(): string {
		return 'initial_standard';
	}

	public function act( Entity_Payment &$entity, Input $input = null ): State_Interface {

	}
}
