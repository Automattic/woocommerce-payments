$check_existing_intention = $this->check_payment_intent_attached_to_order_succeeded( $order );
		if ( is_array( $check_existing_intention ) ) {
			return $check_existing_intention;
		}
