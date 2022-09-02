/* global jQuery, wcpayPaymentRequestParams, wcpayPaymentRequestPayForOrderParams, wc_add_to_cart_variation_params */
/**
 * External dependencies
 */
import { doAction } from '@wordpress/hooks';
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

import { getPaymentRequest, displayLoginConfirmation } from './utils';

jQuery( ( $ ) => {
	// Don't load if blocks checkout is being loaded.
	if ( wcpayPaymentRequestParams.has_block ) {
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

	/**
	 * Object to handle Stripe payment forms.
	 */
	const wcpayPaymentRequest = {
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

				if ( 0 < value.length ) {
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

			const data = {
				product_id: productId,
				qty: $( '.quantity .qty' ).val(),
				attributes: $( '.variations_form' ).length
					? wcpayPaymentRequest.getAttributes().data
					: [],
			};

			// Add addons data to the POST body
			const formData = $( 'form.cart' ).serializeArray();
			$.each( formData, ( i, field ) => {
				if ( /^addon-/.test( field.name ) ) {
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

				wcpayPaymentRequest.attachPaymentRequestButtonEventListeners(
					prButton,
					paymentRequest
				);
				wcpayPaymentRequest.showPaymentRequestButton( prButton );
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

			const addons =
				$( '#product-addons-total' ).data( 'price_data' ) || [];
			const addonValue = addons.reduce(
				( sum, addon ) => sum + addon.cost,
				0
			);

			const data = {
				product_id: productId,
				qty: $( '.quantity .qty' ).val(),
				attributes: $( '.variations_form' ).length
					? wcpayPaymentRequest.getAttributes().data
					: [],
				addon_value: addonValue,
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
							wc_add_to_cart_variation_params.i18n_unavailable_text
						);
					} else if (
						addToCartButton.is( '.wc-variation-selection-needed' )
					) {
						window.alert(
							wc_add_to_cart_variation_params.i18n_make_a_selection_text
						);
					}
					return;
				}

				if ( 0 < paymentRequestError.length ) {
					evt.preventDefault();
					window.alert( paymentRequestError );
					return;
				}

				wcpayPaymentRequest.addToCart();
			} );

			$( document.body ).on( 'woocommerce_variation_has_changed', () => {
				wcpayPaymentRequest.blockPaymentRequestButton();

				$.when( wcpayPaymentRequest.getSelectedProductData() )
					.then( ( response ) => {
						$.when(
							paymentRequest.update( {
								total: response.total,
								displayItems: response.displayItems,
							} )
						).then( () => {
							wcpayPaymentRequest.unblockPaymentRequestButton();
						} );
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

			$( '.quantity' ).on(
				'input',
				'.qty',
				wcpayPaymentRequest.debounce( 250, () => {
					wcpayPaymentRequest.blockPaymentRequestButton();
					paymentRequestError = [];

					$.when( wcpayPaymentRequest.getSelectedProductData() ).then(
						( response ) => {
							if ( response.error ) {
								paymentRequestError = [ response.error ];
								wcpayPaymentRequest.unblockPaymentRequestButton();
							} else {
								$.when(
									paymentRequest.update( {
										total: response.total,
										displayItems: response.displayItems,
									} )
								).then( () => {
									wcpayPaymentRequest.unblockPaymentRequestButton();
								} );
							}
						}
					);
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
			} );
		},

		getElements: () => {
			return $(
				'#wcpay-payment-request-wrapper,#wcpay-payment-request-button-separator'
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
		 * Initialize event handlers and UI state
		 */
		init: () => {
			if ( wcpayPaymentRequestParams.is_pay_for_order ) {
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
		},
	};

	wcpayPaymentRequest.init();

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_cart_totals', () => {
		wcpayPaymentRequest.init();
	} );

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_checkout', () => {
		wcpayPaymentRequest.init();
	} );
} );
