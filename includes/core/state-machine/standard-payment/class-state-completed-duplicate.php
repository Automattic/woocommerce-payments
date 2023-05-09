<?php

namespace WCPay\Core\State_Machine;

class Completed_Duplicate_State extends Final_State {
	public function get_id(): string {
		return 'completed_duplicate';
	}
}
