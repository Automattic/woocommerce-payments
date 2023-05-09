<?php

namespace WCPay\Core\State_Machine;

class Validate_Data_State extends Happy_State {
	public function get_id(): string {
		return 'validate_data';
	}

	public function act( Entity_Payment &$entity, Input $input = null ): State_Interface {

	}
}
