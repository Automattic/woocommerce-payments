jQuery( function ( $ ) {
	'use strict';

	var wc_payments_plugin = {
		init: function () {
			this.init_deactivate_wc_subscriptions_warning();
			this.init_deactivate_wcpay_warning();
		},

		// Initialise handlers for WC Pay deactivate warning.
		init_deactivate_wcpay_warning() {
			// Intercept click on WCPay deactivate link to show modal.
			$( '#deactivate-woocommerce-payments' ).on(
				'click',
				this.display_wcpay_warning
			);
			// Resume deactivate when user confirms modal.
			$( document ).on(
				'click',
				'#wcpay-plugin-deactivate-modal-submit',
				this.redirect_deactivate_wcpay
			);
		},
		// Show a modal to warn merchant that disabling WCPay plugin may leave Stripe subscriptions active (and renewing).
		display_wcpay_warning: function ( event ) {
			event.preventDefault();

			$( this ).WCBackboneModal( {
				template: 'wcpay-plugin-deactivate-warning',
			} );

			return false;
		},
		// Trigger deactivate flow for WCPay.
		redirect_deactivate_wcpay: function ( event ) {
			$( '#wcpay-plugin-deactivate-modal-submit' ).addClass( 'busy' );

			window.location = $( '#deactivate-woocommerce-payments' ).attr(
				'href'
			);
		},

		// Initialise handlers for Woo Subscriptions deactivate warning.
		init_deactivate_wc_subscriptions_warning() {
			// Intercept click on Woo Subscriptions deactivate link to show modal.
			$( '#deactivate-' + this.get_woo_subscriptions_plugin_slug() ).on(
				'click',
				this.display_wcs_warning
			);
			// Resume deactivate when user confirms modal.
			$( document ).on(
				'click',
				'#wcpay-subscriptions-plugin-deactivation-submit',
				this.redirect_deactivate_wc_subscriptions
			);
		},
		// Show a modal to warn merchant that disabling WC Subscriptions plugin will switch to WCPay.
		display_wcs_warning: function ( event ) {
			event.preventDefault();

			$( this ).WCBackboneModal( {
				template: 'wcpay-subscriptions-plugin-warning',
			} );

			return false;
		},
		// Trigger deactivate flow for WC Subscriptions.
		redirect_deactivate_wc_subscriptions: function ( event ) {
			$( '#wcpay-subscriptions-plugin-deactivation-submit' ).addClass(
				'busy'
			);

			window.location = $(
				'#deactivate-' +
					wc_payments_plugin.get_woo_subscriptions_plugin_slug()
			).attr( 'href' );
		},
		// Gets the Woo Subscriptions plugin slug. When the ite is connected to WooCommerce.com, the slug is different and includes a woocommerce-com- prefix.
		get_woo_subscriptions_plugin_slug() {
			const element = document.querySelector(
				'[data-slug="woocommerce-com-woocommerce-subscriptions"]'
			);

			if ( element ) {
				return 'woocommerce-com-woocommerce-subscriptions';
			} else {
				return 'woocommerce-subscriptions';
			}
		},
	};

	wc_payments_plugin.init();
} );
