<?php

namespace WCPay\Core\State_Machine;

class Confirming_State extends Happy_State {
	public function get_id(): string {
		return 'confirming';
	}

	public function act( Entity_Payment &$entity, Input $input = null ): State_Interface {

	}
}
