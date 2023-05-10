<?php
namespace WCPay\Payment_Process;

use Exception;
use WC_Order;
use WCPay\Payment_Process\Payment;

interface Strategy {
	public function process( Payment $payment ): array;
}

class Create_and_Confirm_Intent implements Strategy {
	public function process( Payment $payment ) {
		return [];
	}
}

class Payment_Process {
	protected $strategy;

	public function set_strategy( Strategy $strategy ) {
		$this->strategy = $strategy;
	}

	public function process( Payment $payment ) {
		if ( ! isset( $this->strategy ) ) {
			throw new Exception( 'A strategy is requried to process payments' );
		}

		return $this->strategy->process( $payment );
	}
}

create => class Verified_Payment {}
$verified_payment->prepare(); => class Prepared_Payment {}
$prepared_payment->process(); // insert strategy here
	$authentication_needed_payment->authenticate(); => class Processed_Payment
{
	$successful_payment->store(); => class
	$failed_payment->store();
} => Uncaptured_Payment|Captured_Payment

$uncaptured_payment->capture();
$uncaptured_payment->cancel_authorization();

$captured_payment->refund();



class Gateway {
	public function process_payment( WC_Order $order ) {
		$payment = new Payment();
		$process = new Payment_Process();


		$process->verify();
		$process->prepare();

		$process->set_strategy( new Create_and_Confirm_Intent() );
		$process->process( $payment );

		$process->complete();


	}
}
