<?php

namespace WCPay\Core\State_Machine;

class Prepare_Data_State extends Happy_State {
	public function act( Entity_Payment &$entity, Input $input = null ): State {
		/**
		 * - prepare_metadata
		 * - prepare_customer_details
		 */
	}
}
