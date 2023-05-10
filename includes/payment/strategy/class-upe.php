<?php
// Completion: UPE orders might have a customer, and mode, but not have a payment method yet.
if ( ! is_null( $this->context->get_payment_method() ) ) {
	$this->order_service->set_payment_method_id_for_order( $order, $this->context->get_payment_method()->get_id() );
}
