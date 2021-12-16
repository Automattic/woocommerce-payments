jQuery( function ( $ ) {
	'use strict';

	var wc_payments_product = {
		init: function () {
			// Workaround to prevent duplicate WCPay subscriptions when submit button is spammed in Chrome.
			$( '#publish' ).on( 'click', function ( e ) {
				if (
					$( 'select#product-type option:selected' )
						.text()
						.includes( 'subscription' )
				) {
					$( this ).attr( 'disabled', true );
					$( this ).submit();
				}
			} );
		},
	};

	wc_payments_product.init();
} );
