/* global jQuery, wcpayPaymentRequestParams, wcpayPaymentRequestPayForOrderParams */
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
		wcpayPaymentRequestParams.has_block &&
		! wcpayPaymentRequestParams.is_pay_for_order
	) {
		return;
	}

	const publishableKey = wcpayPaymentRequestParams.stripe.publishableKey;

	if ( ! publishableKey ) {
		// If no configuration is present, probably this is not the checkout page.
		return;
	}

	const api = new WCPayAPI(
		{
			publishableKey,
			accountId: wcpayPaymentRequestParams.stripe.accountId,
			locale: wcpayPaymentRequestParams.stripe.locale,
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
			const paymentRequest = getPaymentRequest( options );
			const elements = api.getStripe().elements();
			const prButton = wcpayPaymentRequest.createPaymentRequestButton(
				elements,
				paymentRequest
			);

			const doActionPaymentRequestAvailability = ( args ) => {
				doAction( 'wcpay.payment-request.availability', args );
			};

			// Check the availability of the Payment Request API first.
			paymentRequest.canMakePayment().then( ( result ) => {
				if ( ! result ) {
					doActionPaymentRequestAvailability( {
						paymentRequestType: null,
					} );
					return;
				}

				// TODO: Don't display custom button when paymentRequestType
				// is `apple_pay` or `google_pay`.
				if ( result.applePay ) {
					paymentRequestType = 'apple_pay';
				} else if ( result.googlePay ) {
					paymentRequestType = 'google_pay';
				} else {
					paymentRequestType = 'payment_request_api';
				}

				doActionPaymentRequestAvailability( {
					paymentRequestType: paymentRequestType,
				} );

				trackPaymentRequestButtonLoad(
					wcpayPaymentRequestParams.button_context
				);

				wcpayPaymentRequest.attachPaymentRequestButtonEventListeners(
					prButton,
					paymentRequest
				);
				wcpayPaymentRequest.showPaymentRequestButton( prButton );
			} );

			paymentRequest.on( 'cancel', () => {
				wcpayPaymentRequest.paymentAborted = true;
			} );

			paymentRequest.on( 'shippingaddresschange', ( event ) =>
				shippingAddressChangeHandler( api, event )
			);

			paymentRequest.on( 'shippingoptionchange', ( event ) =>
				shippingOptionChangeHandler( api, event )
			);

			paymentRequest.on( 'paymentmethod', ( event ) => {
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
		 * Creates stripe paymentRequest element or connects to custom button
		 *
		 * @param {Object} elements       Stripe elements instance.
		 * @param {Object} paymentRequest Stripe paymentRequest object.
		 *
		 * @return {Object} Stripe paymentRequest element or custom button jQuery element.
		 */
		createPaymentRequestButton: ( elements, paymentRequest ) => {
			return elements.create( 'paymentRequestButton', {
				paymentRequest: paymentRequest,
				style: {
					paymentRequestButton: {
						type: wcpayPaymentRequestParams.button.type,
						theme: wcpayPaymentRequestParams.button.theme,
						height: wcpayPaymentRequestParams.button.height + 'px',
					},
				},
			} );
		},

		attachPaymentRequestButtonEventListeners: (
			prButton,
			paymentRequest
		) => {
			if ( wcpayPaymentRequestParams.is_product_page ) {
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

			prButton.on( 'click', ( evt ) => {
				trackPaymentRequestButtonClick( 'product' );

				// If login is required for checkout, display redirect confirmation dialog.
				if ( wcpayPaymentRequestParams.login_confirmation ) {
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
			} );

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
							wcpayPaymentRequestParams.product.needs_shipping ===
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
								wcpayPaymentRequestParams.product
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
				if ( wcpayPaymentRequestParams.login_confirmation ) {
					evt.preventDefault();
					displayLoginConfirmation( paymentRequestType );
				}
				trackPaymentRequestButtonClick(
					wcpayPaymentRequestParams.button_context
				);
			} );
		},

		getElements: () => {
			return $(
				'.wcpay-payment-request-wrapper,#wcpay-payment-request-button-separator'
			);
		},

		hide: () => {
			wcpayPaymentRequest.getElements().hide();
		},

		show: () => {
			wcpayPaymentRequest.getElements().show();
		},

		showPaymentRequestButton: ( prButton ) => {
			if ( $( '#wcpay-payment-request-button' ).length ) {
				wcpayPaymentRequest.show();
				prButton.mount( '#wcpay-payment-request-button' );
			}
		},

		blockPaymentRequestButton: () => {
			// check if element isn't already blocked before calling block() to avoid blinking overlay issues
			// blockUI.isBlocked is either undefined or 0 when element is not blocked
			if (
				$( '#wcpay-payment-request-button' ).data( 'blockUI.isBlocked' )
			) {
				return;
			}

			$( '#wcpay-payment-request-button' ).block( { message: null } );
		},

		unblockPaymentRequestButton: () => {
			wcpayPaymentRequest.show();
			$( '#wcpay-payment-request-button' ).unblock();
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
			wcpayPaymentRequestParams.product.needs_shipping =
				response.needs_shipping;
			wcpayPaymentRequestParams.product.total = response.total;
			wcpayPaymentRequestParams.product.displayItems =
				response.displayItems;
			wcpayPaymentRequest.init();
		},

		/**
		 * Initialize event handlers and UI state
		 */
		init: () => {
			if ( wcpayPaymentRequestParams.is_pay_for_order ) {
				if ( ! window.wcpayPaymentRequestPayForOrderParams ) {
					return;
				}

				const {
					total: { amount: total },
					displayItems,
					order,
				} = wcpayPaymentRequestPayForOrderParams;

				wcpayPaymentRequest.startPaymentRequest( {
					stripe: api.getStripe(),
					requestShipping: false,
					total,
					displayItems,
					handler: payForOrderHandler( order ),
				} );
			} else if ( wcpayPaymentRequestParams.is_product_page ) {
				wcpayPaymentRequest.startPaymentRequest( {
					stripe: api.getStripe(),
					total: wcpayPaymentRequestParams.product.total.amount,
					requestShipping:
						wcpayPaymentRequestParams.product.needs_shipping,
					displayItems:
						wcpayPaymentRequestParams.product.displayItems,
				} );
			} else {
				// If this is the cart or checkout page, we need to request the
				// cart details for the payment request.
				api.paymentRequestGetCartDetails().then( ( cart ) => {
					wcpayPaymentRequest.startPaymentRequest( {
						stripe: api.getStripe(),
						total: cart.total.amount,
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
		! wcpayPaymentRequestParams.is_checkout_page ||
		wcpayPaymentRequestParams.is_pay_for_order
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
					wcpayPaymentRequestParams.product.total = response.total;
					wcpayPaymentRequestParams.product.displayItems =
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
