<?php
if ( $this->failed_transaction_rate_limiter->is_limited() ) {
	// Throwing an exception instead of adding an error notice
	// makes the error notice show up both in the regular and block checkout.
	throw new Exception( __( 'Your payment was not processed.', 'woocommerce-payments' ) );
}
