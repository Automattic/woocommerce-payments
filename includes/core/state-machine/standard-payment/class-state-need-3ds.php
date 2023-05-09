<?php

namespace WCPay\Core\State_Machine;

class Need_3ds_State extends Async_State {
	public function get_id(): string {
		return 'need_3ds';
	}

	public function act( Entity_Payment &$entity, Input $input = null ): State_Interface {

	}
}
