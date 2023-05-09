<?php

namespace WCPay\Core\State_Machine;

class Start_Processing_State extends Happy_State {
	public function get_id(): string {
		return 'start_processing';
	}

	public function act( Entity_Payment &$entity, Input $input = null ): State_Interface {

	}
}
