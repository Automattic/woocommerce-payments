<?php

namespace WCPay\Core\State_Machine;

class Input_Start_Payment_Standard extends Input {


	const KEY_PAYMENT_METHOD = 'payment_method';
	public function set_payment_method( string $payment_method ) {
		$this->set( self::KEY_PAYMENT_METHOD, $payment_method );
	}

	public function get_payment_method(): string {
		return $this->get( self::KEY_PAYMENT_METHOD );
	}
}
