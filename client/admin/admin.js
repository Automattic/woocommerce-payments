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
		const data = {
			action: 'wcs_get_saved_credit_cards',
			customer: $( this ).val(),
			nonce: wcpay_admin.get_cards_tokens_nonce,
		};

		$.ajax( {
			url: ajaxUrl,
			data: data,
			dataType: 'json',
			type: 'POST',
			success: function ( cards ) {
				const cardsSelect = $(
					'#' +
						$.escapeSelector(
							'_payment_method_meta[woocommerce_payments][wc_order_tokens][token]'
						)
				).html( '' );

				cardsSelect.append(
					$( '<option>', {
						value: 0,
						text: __(
							'Please select a payment method',
							'woocommerce-payments'
						),
					} )
				);

				$.each( cards, function ( i, card ) {
					cardsSelect.append(
						$( '<option>', {
							value: card.tokenId,
							text: card.displayName,
						} )
					);
				} );
			},
		} );
	} );
} );
