/* global jQuery */
import { initializeBnplSiteMessaging } from './stripe-element';
import request from 'wcpay/checkout/utils/request';
import { buildAjaxURL } from 'wcpay/utils/express-checkout';

jQuery( async function ( $ ) {
	if (
		! window.wcpayStripeSiteMessaging ||
		window.wcpayStripeSiteMessaging.isCartBlock
	) {
		return;
	}

	const { shouldInitializePMME } = window.wcpayStripeSiteMessaging;

	if ( ! shouldInitializePMME ) {
		const paymentMessageContainer = document.getElementById(
			'payment-method-message'
		);
		paymentMessageContainer.style.setProperty( 'display', 'none' );
		return;
	}

	const { productVariations, productId, isCart } =
		window.wcpayStripeSiteMessaging;

	let baseProductAmount;
	let productCurrency;

	if ( ! isCart ) {
		const { amount, currency } = productVariations[ productId ];

		baseProductAmount = amount || 0;
		productCurrency = currency;
	}

	const QUANTITY_INPUT_SELECTOR = '.quantity input[type=number]';
	const SINGLE_VARIATION_SELECTOR = '.single_variation_wrap';
	const VARIATIONS_SELECTOR = '.variations';
	const RESET_VARIATIONS_SELECTOR = '.reset_variations';
	const VARIATION_ID_SELECTOR = 'input[name="variation_id"]';

	const quantityInput = $( QUANTITY_INPUT_SELECTOR );
	const bnplPaymentMessageElement = await initializeBnplSiteMessaging();
	const hasVariations = Object.keys( productVariations ).length > 1;

	const parseIntOrReturnZero = ( value ) => {
		const result = parseInt( value, 10 );
		return isNaN( result ) ? 0 : result;
	};

	const updateBnplPaymentMessage = async (
		amount,
		currency,
		quantity = 1
	) => {
		const totalAmount =
			parseIntOrReturnZero( amount ) * parseIntOrReturnZero( quantity );
		if ( totalAmount <= 0 || ! currency ) {
			return;
		}
		await bnplPaymentMessageElement?.update( {
			amount: totalAmount,
			currency,
		} );
	};

	const resetBnplPaymentMessage = () => {
		updateBnplPaymentMessage(
			baseProductAmount,
			productCurrency,
			quantityInput.val()
		);
	};

	const bnplGetCartTotal = () => {
		return request(
			buildAjaxURL(
				window.wcpayStripeSiteMessaging.wcAjaxUrl,
				'get_cart_total'
			),
			{
				security: window.wcpayStripeSiteMessaging.nonce.get_cart_total,
			}
		);
	};

	const isBnplAvailable = ( price, currency, country ) => {
		return request(
			buildAjaxURL(
				window.wcpayStripeSiteMessaging.wcAjaxUrl,
				'check_bnpl_availability'
			),
			{
				security:
					window.wcpayStripeSiteMessaging.nonce.is_bnpl_available,
				price: price,
				currency: currency,
				country: country,
			}
		);
	};

	quantityInput.on( 'change', async ( event ) => {
		let amount = baseProductAmount;
		const variationId = $( VARIATION_ID_SELECTOR ).val();

		if (
			hasVariations &&
			productVariations.hasOwnProperty( variationId )
		) {
			amount = productVariations[ variationId ]?.amount;
		}

		updateBnplPaymentMessage( amount, productCurrency, event.target.value );

		try {
			const response = await isBnplAvailable(
				amount * event.target.value,
				productCurrency,
				window.wcpayStripeSiteMessaging.country
			);
			if ( response.success && response.data.is_available ) {
				$( '#payment-method-message' ).slideDown();
			} else {
				$( '#payment-method-message' ).slideUp();
			}
		} catch {
			// Keep the current message if the availability check fails.
		}
	} );

	$( document.body ).on( 'updated_cart_totals', () => {
		$( '#payment-method-message' ).before(
			'<div class="pmme-loading"></div>'
		);
		$( '#payment-method-message' ).hide();
		bnplGetCartTotal().then( ( response ) => {
			window.wcpayStripeSiteMessaging.cartTotal = response.total;
			initializeBnplSiteMessaging().then( () => {
				setTimeout( () => {
					$( '.pmme-loading' ).remove();
					$( '#payment-method-message' ).show();
					$( '#payment-method-message' ).addClass( 'pmme-updated' );
				}, 1000 );
			} );
		} );
	} );

	if ( hasVariations ) {
		$( SINGLE_VARIATION_SELECTOR ).on(
			'show_variation',
			( event, variation ) => {
				if ( ! productVariations[ variation.variation_id ] ) {
					return;
				}

				updateBnplPaymentMessage(
					productVariations[ variation.variation_id ].amount,
					productCurrency,
					quantityInput.val()
				);
			}
		);

		$( VARIATIONS_SELECTOR ).on( 'change', ( event ) => {
			if ( event.target.value === '' ) {
				resetBnplPaymentMessage();
			}
		} );

		$( RESET_VARIATIONS_SELECTOR ).on( 'click', resetBnplPaymentMessage );
	}
} );
