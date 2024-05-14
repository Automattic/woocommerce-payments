/* global jQuery, wcpayExpressCheckoutParams, wcpayPaymentRequestPayForOrderParams */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { doAction } from '@wordpress/hooks';
import { debounce } from 'lodash';
/**
 * Internal dependencies
 */
import WCPayAPI from '../checkout/api';

import {
	shippingAddressChangeHandler,
	shippingOptionChangeHandler,
	paymentMethodHandler,
	payForOrderHandler,
} from './event-handlers.js';
import '../checkout/express-checkout-buttons.scss';
import { recordUserEvent } from 'tracks';

import { getPaymentRequest, displayLoginConfirmation } from './utils';

jQuery( ( $ ) => {
	// Don't load if blocks checkout is being loaded.
	if (
		wcpayExpressCheckoutParams.has_block &&
		! wcpayExpressCheckoutParams.is_pay_for_order
	) {
		return;
	}

	const publishableKey = wcpayExpressCheckoutParams.stripe.publishableKey;

	if ( ! publishableKey ) {
		// If no configuration is present, probably this is not the checkout page.
		return;
	}

	const api = new WCPayAPI(
		{
			publishableKey,
			accountId: wcpayExpressCheckoutParams.stripe.accountId,
			locale: wcpayExpressCheckoutParams.stripe.locale,
		},
		// A promise-based interface to jQuery.post.
		( url, args ) => {
			return new Promise( ( resolve, reject ) => {
				jQuery.post( url, args ).then( resolve ).fail( reject );
			} );
		}
	);

	let paymentRequestType;

	// Track the payment request button click event.
	const trackPaymentRequestButtonClick = ( source ) => {
		const paymentRequestTypeEvents = {
			google_pay: 'gpay_button_click',
			apple_pay: 'applepay_button_click',
		};

		if ( paymentRequestTypeEvents.hasOwnProperty( paymentRequestType ) ) {
			const event = paymentRequestTypeEvents[ paymentRequestType ];
			recordUserEvent( event, { source } );
		}
	};

	// Track the payment request button load event.
	const trackPaymentRequestButtonLoad = debounce( ( source ) => {
		const paymentRequestTypeEvents = {
			google_pay: 'gpay_button_load',
			apple_pay: 'applepay_button_load',
		};

		if ( paymentRequestTypeEvents.hasOwnProperty( paymentRequestType ) ) {
			const event = paymentRequestTypeEvents[ paymentRequestType ];
			recordUserEvent( event, { source } );
		}
	}, 1000 );

	/**
	 * Object to handle Stripe payment forms.
	 */
	const wcpayPaymentRequest = {
		/**
		 * Whether the payment was aborted by the customer.
		 */
		paymentAborted: false,

		getAttributes: function () {
			const select = $( '.variations_form' ).find( '.variations select' );
			const data = {};
			let count = 0;
			let chosen = 0;

			select.each( function () {
				const attributeName =
					$( this ).data( 'attribute_name' ) ||
					$( this ).attr( 'name' );
				const value = $( this ).val() || '';

				if ( value.length > 0 ) {
					chosen++;
				}

				count++;
				data[ attributeName ] = value;
			} );

			return {
				count: count,
				chosenCount: chosen,
				data: data,
			};
		},

		/**
		 * Abort payment and display error messages.
		 *
		 * @param {PaymentResponse} payment Payment response instance.
		 * @param {string}          message Error message to display.
		 */
		abortPayment: ( payment, message ) => {
			payment.complete( 'fail' );

			$( '.woocommerce-error' ).remove();

			const $container = $( '.woocommerce-notices-wrapper' ).first();

			if ( $container.length ) {
				$container.append(
					$( '<div class="woocommerce-error" />' ).text( message )
				);

				$( 'html, body' ).animate(
					{
						scrollTop: $container
							.find( '.woocommerce-error' )
							.offset().top,
					},
					600
				);
			}
		},

		/**
		 * Complete payment.
		 *
		 * @param {string} url Order thank you page URL.
		 */
		completePayment: ( url ) => {
			wcpayPaymentRequest.block();
			window.location = url;
		},

		block: () => {
			$.blockUI( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6,
				},
			} );
		},

		/**
		 * Adds the item to the cart and return cart details.
		 *
		 * @return {Promise} Promise for the request to the server.
		 */
		addToCart: () => {
			let productId = $( '.single_add_to_cart_button' ).val();

			// Check if product is a variable product.
			if ( $( '.single_variation_wrap' ).length ) {
				productId = $( '.single_variation_wrap' )
					.find( 'input[name="product_id"]' )
					.val();
			}

			if ( $( '.wc-bookings-booking-form' ).length ) {
				productId = $( '.wc-booking-product-id' ).val();
			}

			const data = {
				product_id: productId,
				qty: $( '.quantity .qty' ).val(),
				attributes: $( '.variations_form' ).length
					? wcpayPaymentRequest.getAttributes().data
					: [],
			};

			// Add extension data to the POST body
			const formData = $( 'form.cart' ).serializeArray();
			$.each( formData, ( i, field ) => {
				if ( /^(addon-|wc_)/.test( field.name ) ) {
					if ( /\[\]$/.test( field.name ) ) {
						const fieldName = field.name.substring(
							0,
							field.name.length - 2
						);
						if ( data[ fieldName ] ) {
							data[ fieldName ].push( field.value );
						} else {
							data[ fieldName ] = [ field.value ];
						}
					} else {
						data[ field.name ] = field.value;
					}
				}
			} );

			return api.paymentRequestAddToCart( data );
		},

		/**
		 * Starts the payment request
		 *
		 * @param {Object} options Payment request options.
		 */
		startPaymentRequest: ( options ) => {
			const elements = api.getStripe().elements( {
				mode: options?.mode ?? 'payment',
				amount: options?.total,
				currency: options?.currency,
			} );

			const eceButton = wcpayPaymentRequest.createPaymentRequestButton(
				elements,
				{
					layout: 'auto',
					buttonType: {
						googlePay: 'book',
						applePay: 'book',
						paypal: 'buynow',
					},
					buttonTheme: {
						applePay: 'black'
					},
				}
			);

			wcpayPaymentRequest.showPaymentRequestButton( eceButton );

			wcpayPaymentRequest.attachPaymentRequestButtonEventListeners(
				eceButton
			);

			eceButton.on( 'click', function ( event ) {
				const clickOptions = {
					business: {
						name: 'Mikes Bikes',
					},
					lineItems: [
						{ name: 'Bike', amount: 200 },
						{ name: 'Helmet', amount: 300 },
					],
					shippingAddressRequired: true,
					shippingRates: [
						{
							id: '1',
							amount: 500,
							displayName: 'Standard Shipping',
						},
						{
							id: '2',
							amount: 1000,
							displayName: 'Expedited Shipping',
						},
					],
				};
				event.resolve( clickOptions );
			} );

			eceButton.on( 'cancel', () => {
				wcpayPaymentRequest.paymentAborted = true;
			} );

			eceButton.on( 'shippingaddresschange', ( event ) => {
				shippingAddressChangeHandler( api, event );
			} );

			eceButton.on( 'shippingratechange', function ( event ) {
				shippingOptionChangeHandler( api, event );
			} );

			eceButton.on( 'paymentmethod', ( event ) => {
				const handler = options.handler ?? paymentMethodHandler;

				handler(
					api,
					wcpayPaymentRequest.completePayment,
					wcpayPaymentRequest.abortPayment,
					event
				);
			} );
		},

		getSelectedProductData: () => {
			let productId = $( '.single_add_to_cart_button' ).val();

			// Check if product is a variable product.
			if ( $( '.single_variation_wrap' ).length ) {
				productId = $( '.single_variation_wrap' )
					.find( 'input[name="product_id"]' )
					.val();
			}

			if ( $( '.wc-bookings-booking-form' ).length ) {
				productId = $( '.wc-booking-product-id' ).val();
			}

			const addons =
				$( '#product-addons-total' ).data( 'price_data' ) || [];
			const addonValue = addons.reduce(
				( sum, addon ) => sum + addon.cost,
				0
			);

			// WC Deposits Support.
			const depositObject = {};
			if ( $( 'input[name=wc_deposit_option]' ).length ) {
				depositObject.wc_deposit_option = $(
					'input[name=wc_deposit_option]:checked'
				).val();
			}
			if ( $( 'input[name=wc_deposit_payment_plan]' ).length ) {
				depositObject.wc_deposit_payment_plan = $(
					'input[name=wc_deposit_payment_plan]:checked'
				).val();
			}

			const data = {
				product_id: productId,
				qty: $( '.quantity .qty' ).val(),
				attributes: $( '.variations_form' ).length
					? wcpayPaymentRequest.getAttributes().data
					: [],
				addon_value: addonValue,
				...depositObject,
			};

			return api.paymentRequestGetSelectedProductData( data );
		},

		/**
		 * Creates a wrapper around a function that ensures a function can not
		 * called in rappid succesion. The function can only be executed once and then agin after
		 * the wait time has expired.  Even if the wrapper is called multiple times, the wrapped
		 * function only excecutes once and then blocks until the wait time expires.
		 *
		 * @param {int} wait       Milliseconds wait for the next time a function can be executed.
		 * @param {Function} func       The function to be wrapped.
		 * @param {bool} immediate Overriding the wait time, will force the function to fire everytime.
		 *
		 * @return {Function} A wrapped function with execution limited by the wait time.
		 */
		debounce: ( wait, func, immediate ) => {
			let timeout;
			return function () {
				const context = this,
					args = arguments;
				const later = () => {
					timeout = null;
					if ( ! immediate ) {
						func.apply( context, args );
					}
				};
				const callNow = immediate && ! timeout;
				clearTimeout( timeout );
				timeout = setTimeout( later, wait );
				if ( callNow ) {
					func.apply( context, args );
				}
			};
		},

		/**
		 * Creates Stripe Express Checkout Element.
		 *
		 * @param {Object} elements       Stripe elements instance.
		 * @param {Object} options 		  Options for creating the Express Checkout Element.
		 *
		 * @return {Object} Stripe Express Checkout Element.
		 */
		createPaymentRequestButton: ( elements, options ) => {
			return elements.create( 'expressCheckout', options );
		},

		attachPaymentRequestButtonEventListeners: (
			prButton,
			paymentRequest
		) => {
			if ( wcpayExpressCheckoutParams.is_product_page ) {
				wcpayPaymentRequest.attachProductPageEventListeners(
					prButton,
					paymentRequest
				);
			} else {
				wcpayPaymentRequest.attachCartPageEventListeners( prButton );
			}
		},

		attachProductPageEventListeners: ( prButton, paymentRequest ) => {
			let paymentRequestError = [];
			const addToCartButton = $( '.single_add_to_cart_button' );

			/* prButton.on( 'click', ( evt ) => {
				trackPaymentRequestButtonClick( 'product' );

				// If login is required for checkout, display redirect confirmation dialog.
				if ( wcpayExpressCheckoutParams.login_confirmation ) {
					evt.preventDefault();
					displayLoginConfirmation( paymentRequestType );
					return;
				}

				// First check if product can be added to cart.
				if ( addToCartButton.is( '.disabled' ) ) {
					evt.preventDefault(); // Prevent showing payment request modal.
					if (
						addToCartButton.is( '.wc-variation-is-unavailable' )
					) {
						window.alert(
							window?.wc_add_to_cart_variation_params
								?.i18n_unavailable_text ||
								__(
									'Sorry, this product is unavailable. Please choose a different combination.',
									'woocommerce-payments'
								)
						);
					} else {
						window.alert(
							__(
								'Please select your product options before proceeding.',
								'woocommerce-payments'
							)
						);
					}
					return;
				}

				if ( paymentRequestError.length > 0 ) {
					evt.preventDefault();
					window.alert( paymentRequestError );
					return;
				}

				wcpayPaymentRequest.addToCart();
			} ); */

			// WooCommerce Deposits support.
			// Trigger the "woocommerce_variation_has_changed" event when the deposit option is changed.
			$(
				'input[name=wc_deposit_option],input[name=wc_deposit_payment_plan]'
			).on( 'change', () => {
				$( 'form' )
					.has(
						'input[name=wc_deposit_option],input[name=wc_deposit_payment_plan]'
					)
					.trigger( 'woocommerce_variation_has_changed' );
			} );

			$( document.body ).on( 'woocommerce_variation_has_changed', () => {
				wcpayPaymentRequest.blockPaymentRequestButton();

				$.when( wcpayPaymentRequest.getSelectedProductData() )
					.then( ( response ) => {
						/**
						 * If the customer aborted the payment request, we need to re init the payment request button to ensure the shipping
						 * options are refetched. If the customer didn't abort the payment request, and the product's shipping status is
						 * consistent, we can simply update the payment request button with the new total and display items.
						 */
						if (
							! wcpayPaymentRequest.paymentAborted &&
							wcpayExpressCheckoutParams.product.needs_shipping ===
								response.needs_shipping
						) {
							paymentRequest.update( {
								total: response.total,
								displayItems: response.displayItems,
							} );
						} else {
							wcpayPaymentRequest.reInitPaymentRequest(
								response
							);
						}

						wcpayPaymentRequest.unblockPaymentRequestButton();
					} )
					.catch( () => {
						wcpayPaymentRequest.hide();
					} );
			} );

			// Block the payment request button as soon as an "input" event is fired, to avoid sync issues
			// when the customer clicks on the button before the debounced event is processed.
			$( '.quantity' ).on( 'input', '.qty', () => {
				wcpayPaymentRequest.blockPaymentRequestButton();
			} );

			$( '.quantity' )
				.off( 'input', '.qty' )
				.on(
					'input',
					'.qty',
					wcpayPaymentRequest.debounce( 250, () => {
						wcpayPaymentRequest.blockPaymentRequestButton();
						paymentRequestError = [];

						$.when(
							wcpayPaymentRequest.getSelectedProductData()
						).then( ( response ) => {
							if (
								! wcpayPaymentRequest.paymentAborted &&
								wcpayExpressCheckoutParams.product
									.needs_shipping === response.needs_shipping
							) {
								paymentRequest.update( {
									total: response.total,
									displayItems: response.displayItems,
								} );
							} else {
								wcpayPaymentRequest.reInitPaymentRequest(
									response
								);
							}
							wcpayPaymentRequest.unblockPaymentRequestButton();
						} );
					} )
				);
		},

		attachCartPageEventListeners: ( prButton ) => {
			prButton.on( 'click', ( evt ) => {
				// If login is required for checkout, display redirect confirmation dialog.
				if ( wcpayExpressCheckoutParams.login_confirmation ) {
					evt.preventDefault();
					displayLoginConfirmation( paymentRequestType );
				}
				trackPaymentRequestButtonClick(
					wcpayExpressCheckoutParams.button_context
				);
			} );
		},

		getElements: () => {
			return $(
				'.wcpay-payment-request-wrapper,#wcpay-express-checkout-button-separator'
			);
		},

		hide: () => {
			wcpayPaymentRequest.getElements().hide();
		},

		show: () => {
			wcpayPaymentRequest.getElements().show();
		},

		showPaymentRequestButton: ( prButton ) => {
			if ( $( '#wcpay-express-checkout-element' ).length ) {
				wcpayPaymentRequest.show();
				prButton.mount( '#wcpay-express-checkout-element' );
			}
		},

		blockPaymentRequestButton: () => {
			// check if element isn't already blocked before calling block() to avoid blinking overlay issues
			// blockUI.isBlocked is either undefined or 0 when element is not blocked
			if (
				$( '#wcpay-express-checkout-button' ).data(
					'blockUI.isBlocked'
				)
			) {
				return;
			}

			$( '#wcpay-express-checkout-button' ).block( { message: null } );
		},

		unblockPaymentRequestButton: () => {
			wcpayPaymentRequest.show();
			$( '#wcpay-express-checkout-button' ).unblock();
		},

		/**
		 * Re init the payment request button.
		 *
		 * This ensures that when the customer clicks on the payment button, the available shipping options are
		 * refetched based on the selected variable product's data and the chosen address.
		 *
		 *  This is also useful when the customer changes the quantity of a product, as the total and display items
		 *  need to be updated.
		 *
		 * @param {Object} response Response from the server containing the updated product data.
		 */
		reInitPaymentRequest: ( response ) => {
			wcpayExpressCheckoutParams.product.needs_shipping =
				response.needs_shipping;
			wcpayExpressCheckoutParams.product.total = response.total;
			wcpayExpressCheckoutParams.product.displayItems =
				response.displayItems;
			wcpayPaymentRequest.init();
		},

		/**
		 * Initialize event handlers and UI state
		 */
		init: () => {
			if ( wcpayExpressCheckoutParams.is_pay_for_order ) {
				if ( ! window.wcpayPaymentRequestPayForOrderParams ) {
					return;
				}

				wcpayPaymentRequest.startPaymentRequest();
			} else if ( wcpayExpressCheckoutParams.is_product_page ) {
				wcpayPaymentRequest.startPaymentRequest( {
					mode: 'payment',
					total: wcpayExpressCheckoutParams.product.total.amount,
					currency: 'usd',
					requestShipping:
						wcpayExpressCheckoutParams.product.needs_shipping,
					displayItems:
						wcpayExpressCheckoutParams.product.displayItems,
				} );
			} else {
				// If this is the cart or checkout page, we need to request the
				// cart details for the payment request.
				api.paymentRequestGetCartDetails().then( ( cart ) => {
					wcpayPaymentRequest.startPaymentRequest( {
						mode: 'payment',
						total: cart.total.amount,
						currency: 'usd',
						requestShipping: cart.needs_shipping,
						displayItems: cart.displayItems,
					} );
				} );
			}

			// After initializing a new payment request, we need to reset the paymentAborted flag.
			wcpayPaymentRequest.paymentAborted = false;
		},
	};

	// We don't need to initialize payment request on the checkout page now because it will be initialized by updated_checkout event.
	if (
		! wcpayExpressCheckoutParams.is_checkout_page ||
		wcpayExpressCheckoutParams.is_pay_for_order
	) {
		wcpayPaymentRequest.init();
	}

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_cart_totals', () => {
		wcpayPaymentRequest.init();
	} );

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_checkout', () => {
		wcpayPaymentRequest.init();
	} );

	// Handle bookable products on the product page.
	let wcBookingFormChanged = false;

	$( document.body )
		.off( 'wc_booking_form_changed' )
		.on( 'wc_booking_form_changed', () => {
			wcBookingFormChanged = true;
		} );

	// Listen for the WC Bookings wc_bookings_calculate_costs event to complete
	// and add the bookable product to the cart, using the response to update the
	// payment request request params with correct totals.
	$( document ).ajaxComplete( function ( event, xhr, settings ) {
		if ( wcBookingFormChanged ) {
			if (
				settings.url === window.booking_form_params.ajax_url &&
				settings.data.includes( 'wc_bookings_calculate_costs' ) &&
				xhr.responseText.includes( 'SUCCESS' )
			) {
				wcpayPaymentRequest.blockPaymentRequestButton();
				wcBookingFormChanged = false;
				return wcpayPaymentRequest.addToCart().then( ( response ) => {
					wcpayExpressCheckoutParams.product.total = response.total;
					wcpayExpressCheckoutParams.product.displayItems =
						response.displayItems;
					// Empty the cart to avoid having 2 products in the cart when payment request is not used.
					api.paymentRequestEmptyCart( response.bookingId );

					wcpayPaymentRequest.init();

					wcpayPaymentRequest.unblockPaymentRequestButton();
				} );
			}
		}
	} );
} );
