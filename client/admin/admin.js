/* global jQuery */
/* global woocommerce_admin */
/* global wcpay_admin */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

jQuery( function ( $ ) {
	const ajaxUrl = woocommerce_admin.ajax_url;

	$( '#woocommerce-subscription-data #customer_user' ).change( function () {
		const data = new FormData();

		data.append( 'action', 'wcs_get_saved_credit_cards' );
		data.append( 'customer', this.options[ this.selectedIndex ].value );
		data.append( 'nonce', wcpay_admin.get_cards_tokens_nonce );

		fetch( ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data,
		} )
			.then( ( response ) => response.json() )
			.then( ( cards ) => {
				const cardsSelect = document.querySelector(
					'#' +
						CSS.escape(
							'_payment_method_meta[woocommerce_payments][wc_order_tokens][token]'
						)
				);

				let html = '';

				html +=
					'<option value="">' +
					__(
						'Please select a payment method',
						'woocommerce-payments'
					) +
					'</option>';

				for ( const card of cards ) {
					html +=
						'<option value="' +
						card.tokenId +
						'">' +
						card.displayName +
						'</option>';
				}

				cardsSelect.innerHTML = html;
			} )
			.catch( ( error ) => {
				console.log( '[WP Pageviews Plugin]' );
				console.error( error );
			} );
	} );
} );
