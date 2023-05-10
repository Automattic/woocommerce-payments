<?php

namespace WCPay\Core\State_Machine;

class Detect_Duplicate_State extends Happy_State {
	public function get_id(): string {
		return 'detect_duplicate';
	}

	public function act( Entity_Payment &$entity, Input $input = null ): State_Interface {

	}
}
