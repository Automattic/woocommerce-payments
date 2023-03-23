$check_session_order = $this->check_against_session_processing_order( $order );
		if ( is_array( $check_session_order ) ) {
			return $check_session_order;
		}
		$this->maybe_update_session_processing_order( $order_id );
