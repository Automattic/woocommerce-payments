<?php
namespace WCPay\Core\State_Machine;
abstract class Gateway_Example {

	public function process_payment(\WC_Order $order) {

		$payment_storage = new Entity_Storage_Payment();
		$payment_entity = $payment_storage->get_or_create( $order );
		$input = []; // TODO - define input here. May use a DTO class.
		$state_machine = new State_Machine_Standard_Payment( $payment_entity );
	}
}
