<?php

namespace WCPay\Core\State_Machine;

class Input_Process_3ds_Result extends Input {

	const KEY_INTENT_ID_RECEIVED = 'intent_id_received';
	public function set_payment_method( string $payment_method ) {
		$this->set( self::KEY_PAYMENT_METHOD, $payment_method );
	}

	public function get_payment_method(): string {
		return $this->get( self::KEY_PAYMENT_METHOD );
	}

	public function set_intent_id_received( string $intent_id_received ) {
		$this->set( self::KEY_INTENT_ID_RECEIVED, $intent_id_received );
	}

	public function get_intent_id_received(): string {
		return $this->get( self::KEY_INTENT_ID_RECEIVED );
	}
}
