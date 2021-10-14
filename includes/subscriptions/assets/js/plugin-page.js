jQuery( function ( $ ) {
	'use strict';

	var wc_payments_plugin = {
		init: function () {
			$( '#deactivate-woocommerce-subscriptions' ).on(
				'click',
				this.display_modal
			);
			$( document ).on(
				'click',
				'#wcpay-subscriptions-plugin-deactivation-submit',
				this.deactivate_subscriptions
			);
		},
		display_modal: function ( event ) {
			event.preventDefault();

			// Display modal
			$( this ).WCBackboneModal( {
				template: 'wcpay-subscriptions-plugin-warning',
			} );

			return false;
		},
		deactivate_subscriptions: function ( event ) {
			$( '#wcpay-subscriptions-plugin-deactivation-submit' ).addClass(
				'busy'
			);

			window.location = $( '#deactivate-woocommerce-subscriptions' ).attr(
				'href'
			);
		},
	};

	wc_payments_plugin.init();
} );
