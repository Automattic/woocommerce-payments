<?php
abstract class Payment_State {
	protected $context;

	public function __construct( Payment $context ) {
		$this->context = $context;
	}

	public function process() {
		throw new Exception( __( 'A payment can only be processed once!', 'woocommerce-payments' ) );
	}
}

class Payment_State_Initial extends Payment_State {
	public function process() {
		$process = new Payment_Process( $this );

	}
}

class Payment_State_Authorized extends Payment_State {

}

class Payment_State_Captured extends Payment_State {

}

class Payment {
	protected $flags;
	protected $data;
	protected $state;

	public function __construct( string $state = null ) {
		if ( $state ) {
			$this->state = new $state( $this );
		} else {
			$this->state = new Payment_State_Initial( $this );
		}
	}

	public function process() {
		return $this->state->process();
	}
}

class Payment_Process {
	protected Payment $payment;

	public function __construct( Payment $payment ) {
		$this->payment = $payment;
	}
}

class Payment_Process_Strategy {

}

$payment = new Payment();


var_dump( $process );
