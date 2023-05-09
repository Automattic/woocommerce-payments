<?php

namespace WCPay\Core\State_Machine;

class Prepare_Data_State extends Happy_State {
	public function get_id(): string {
		return 'prepare_data';
	}

	public function act( Entity_Payment &$entity, Input $input = null ): State_Interface {
		/**
		 * - prepare_metadata
		 * - prepare_customer_details
		 */
	}
}
