$last_payment_error_code = $updated_payment_intent->get_last_payment_error()['code'] ?? '';
		if ( $this->should_bump_rate_limiter( $last_payment_error_code ) ) {
			// UPE method gives us the error of the previous payment attempt, so we use that for the Rate Limiter.
			$this->failed_transaction_rate_limiter->bump();
		}
