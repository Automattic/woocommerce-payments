jQuery( function ( $ ) {
	'use strict';

	var wc_payments_plugin = {
		init: function () {
			this.init_deactivate_wc_subscriptions_warning();
			this.init_deactivate_wcpay_warning();
		},

		// Initialise handlers for WC Pay deactivate warning.
		init_deactivate_wcpay_warning() {
			// If the store doesn't have active WCPay (Stripe Billing) subscriptions, no warning needed.
			if (
				! wcpay_subscriptions_plugin_screen_data.store_has_active_wcpay_subscriptions
			) {
				return;
			}

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

		// Initialise handlers for WC Subscriptions deactivate warning.
		init_deactivate_wc_subscriptions_warning() {
			// Intercept click on WCS deactivate link to show modal.
			$( '#deactivate-woocommerce-subscriptions' ).on(
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

			window.location = $( '#deactivate-woocommerce-subscriptions' ).attr(
				'href'
			);
		},
	};

	wc_payments_plugin.init();
} );
