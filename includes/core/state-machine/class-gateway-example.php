<?php
namespace WCPay\Core\State_Machine;

/**
 * Simple example for the standard payment.
 */
class Standard_Gateway_Example {
	public function process_payment(\WC_Order $order) {

		// Load the payment entity based on the order.
		$payment_storage = new Entity_Storage_Payment();
		$payment_entity = $payment_storage->load( $order );

		// Build up input object from the HTTP request.
		$input = new Input_Start_Payment_Standard();
		$input->set_payment_method( wp_unslash( $_POST['payment_method'] ?? '' ) );
		// ... Many more inputs here.

		// Set required variables for the state machine, and progress.
		$state_machine = new State_Machine_Standard_Payment( $payment_storage );
		$state_machine->set_initial_state( new Start_Standard_Payment_State() )
			->set_entity( $payment_entity )
			->set_input( $input );
		$processed_entity = $state_machine->progress();

		// current_state at this point can be either:
		// - failed state: General_Failed_State
		// - async state: Need_3ds_State
		// - final states: Completed_State, Completed_Duplicate_State
		$current_state = $processed_entity->get_current_state();
		// TODO: Decide return/output based on the current state.
	}

	/**
	 * Similar to WC_Payment_Gateway_WCPay::update_order_status(),
	 * which is handled after confirming the 3DS/SCA payment.
	 *
	 * @return void
	 */
	public function process_3ds_result() {

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : false;
		$order    = wc_get_order( $order_id );
		if ( ! is_a($order, \WC_Order::class) ) {
			throw new Process_Payment_Exception(
				__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
				'order_not_found'
			);
		}

		// Load the payment entity based on the order.
		$payment_storage = new Entity_Storage_Payment();
		$payment_entity = $payment_storage->load( $order );

		// At this specific point, only process if the current state is Need_3ds_State.
		if( ! is_a( $payment_entity, Need_3ds_State::class ) ) {
			throw new \Exception('Not a 3DS payment.') ;
		}

		// Build up inputs.
		$intent_id_received = isset( $_POST['intent_id'] )
			? sanitize_text_field( wp_unslash( $_POST['intent_id'] ) )
			/* translators: This will be used to indicate an unknown value for an ID. */
			: __( 'unknown', 'woocommerce-payments' );
		$input = new Input_Process_3ds_Result();
		$input->set_intent_id_received( $intent_id_received );

		// Set required variables for the state machine, and progress.
		$state_machine = new State_Machine_Standard_Payment( $payment_storage );
		$state_machine->set_entity( $payment_entity )
		              ->set_input( $input );
		$processed_entity = $state_machine->progress();

		// Based on the state machine config, current_state here can be either:
		// - Completed_State
		// - General_Failed_State.
		$current_state = $processed_entity->get_current_state();
		// TODO: Decide return/output based on the current state.
	}

}
