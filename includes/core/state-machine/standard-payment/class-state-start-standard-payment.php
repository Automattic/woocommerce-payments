<?php

namespace WCPay\Core\State_Machine;

class Start_Standard_Payment_State extends Happy_State {
	public function get_id(): string {
		return 'start_standard_payment';
	}

	public function act( Entity_Payment &$entity, Input $input = null ): State_Interface {

	}
}
