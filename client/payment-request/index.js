/* global jQuery, wcpayPaymentRequestParams, wc_add_to_cart_variation_params */

/**
 * Internal dependencies
 */
import './style.scss';
import WCPayAPI from '../checkout/api';

import {
	shippingAddressChangeHandler,
	shippingOptionChangeHandler,
	paymentMethodHandler,
} from './event-handlers.js';

import {
	shouldUseGooglePayBrand,
	getPaymentRequest,
	canDoPaymentRequest,
} from './utils';

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
				// eslint-disable-next-line camelcase
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

			api.paymentRequestAddToCart( data );
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

			// Check the availability of the Payment Request API first.
			canDoPaymentRequest( paymentRequest ).then( ( result ) => {
				if ( ! result || ! result.canPay ) {
					return;
				}

				// TODO: Don't display custom button when result.requestType
				// is `apple_pay` or `google_pay`.

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

			paymentRequest.on( 'paymentmethod', ( event ) =>
				paymentMethodHandler(
					api,
					wcpayPaymentRequest.completePayment,
					wcpayPaymentRequest.abortPayment,
					event
				)
			);
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
				// eslint-disable-next-line camelcase
				product_id: productId,
				qty: $( '.quantity .qty' ).val(),
				attributes: $( '.variations_form' ).length
					? wcpayPaymentRequest.getAttributes().data
					: [],
				// eslint-disable-next-line camelcase
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
			let button;
			if ( wcpayPaymentRequestParams.button.is_custom ) {
				button = $( wcpayPaymentRequestParams.button.css_selector );
				if ( button.length ) {
					// We fallback to default paymentRequest button if no custom button is found in the UI.
					// Add flag to be sure that created button is custom button rather than fallback element.
					button.data( 'isCustom', true );
					return button;
				}
			}

			if ( wcpayPaymentRequestParams.button.is_branded ) {
				if ( shouldUseGooglePayBrand() ) {
					button = wcpayPaymentRequest.createGooglePayButton();
					// Add flag to be sure that created button is branded rather than fallback element.
					button.data( 'isBranded', true );
					return button;
				}

				// Not implemented branded buttons default to Stripe's button
				// Apple Pay buttons can also fall back to Stripe's button, as it's already branded
				// Set button type to default or buy, depending on branded type, to avoid issues with Stripe
				wcpayPaymentRequestParams.button.type =
					'long' === wcpayPaymentRequestParams.button.branded_type
						? 'buy'
						: 'default';
			}

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

		/**
		 * Checks if button is custom payment request button.
		 *
		 * @param {Object} prButton Stripe paymentRequest element or custom jQuery element.
		 *
		 * @return {boolean} True when prButton is custom button jQuery element.
		 */
		isCustomPaymentRequestButton: ( prButton ) =>
			prButton &&
			'function' === typeof prButton.data &&
			prButton.data( 'isCustom' ),

		isBrandedPaymentRequestButton: ( prButton ) =>
			prButton &&
			'function' === typeof prButton.data &&
			prButton.data( 'isBranded' ),

		createGooglePayButton: () => {
			const type = wcpayPaymentRequestParams.button.branded_type;
			const locale = wcpayPaymentRequestParams.button.locale;
			const height = wcpayPaymentRequestParams.button.height;
			// Allowed themes for Google Pay button image are 'dark' and 'light'.
			const theme =
				'dark' === wcpayPaymentRequestParams.button.theme
					? 'dark'
					: 'light';

			const button = $(
				'<button type="button" id="wcpay-branded-button" aria-label="Google Pay" class="gpay-button"></button>'
			);
			button.css( 'height', height + 'px' );
			// For the button class, `light-outline` is also supported.
			button.addClass(
				wcpayPaymentRequestParams.button.theme + ' ' + type
			);
			if ( 'long' === type ) {
				const url =
					'https://www.gstatic.com/instantbuy/svg/' +
					theme +
					'/' +
					locale +
					'.svg';
				const fallbackUrl =
					'https://www.gstatic.com/instantbuy/svg/' +
					theme +
					'/en.svg';
				// Check if locale GPay button exists, default to en if not
				setBackgroundImageWithFallback( button, url, fallbackUrl );
			}

			return button;
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
				wcpayPaymentRequest.attachCartPageEventListeners(
					prButton,
					paymentRequest
				);
			}
		},

		attachProductPageEventListeners: ( prButton, paymentRequest ) => {
			let paymentRequestError = [];
			const addToCartButton = $( '.single_add_to_cart_button' );

			prButton.on( 'click', ( evt ) => {
				// First check if product can be added to cart.
				if ( addToCartButton.is( '.disabled' ) ) {
					evt.preventDefault(); // Prevent showing payment request modal.
					if (
						addToCartButton.is( '.wc-variation-is-unavailable' )
					) {
						window.alert(
							// eslint-disable-next-line camelcase
							wc_add_to_cart_variation_params.i18n_unavailable_text
						);
					} else if (
						addToCartButton.is( '.wc-variation-selection-needed' )
					) {
						window.alert(
							// eslint-disable-next-line camelcase
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

				if (
					wcpayPaymentRequest.isCustomPaymentRequestButton(
						prButton
					) ||
					wcpayPaymentRequest.isBrandedPaymentRequestButton(
						prButton
					)
				) {
					evt.preventDefault();
					paymentRequest.show();
				}
			} );

			$( document.body ).on( 'woocommerce_variation_has_changed', () => {
				wcpayPaymentRequest.blockPaymentRequestButton( prButton );

				$.when( wcpayPaymentRequest.getSelectedProductData() ).then(
					( response ) => {
						$.when(
							paymentRequest.update( {
								total: response.total,
								displayItems: response.displayItems,
							} )
						).then( () => {
							wcpayPaymentRequest.unblockPaymentRequestButton(
								prButton
							);
						} );
					}
				);
			} );

			// Block the payment request button as soon as an "input" event is fired, to avoid sync issues
			// when the customer clicks on the button before the debounced event is processed.
			$( '.quantity' ).on( 'input', '.qty', () => {
				wcpayPaymentRequest.blockPaymentRequestButton( prButton );
			} );

			$( '.quantity' ).on(
				'input',
				'.qty',
				wcpayPaymentRequest.debounce( 250, () => {
					wcpayPaymentRequest.blockPaymentRequestButton( prButton );
					paymentRequestError = [];

					$.when( wcpayPaymentRequest.getSelectedProductData() ).then(
						( response ) => {
							if ( response.error ) {
								paymentRequestError = [ response.error ];
								wcpayPaymentRequest.unblockPaymentRequestButton(
									prButton
								);
							} else {
								$.when(
									paymentRequest.update( {
										total: response.total,
										displayItems: response.displayItems,
									} )
								).then( () => {
									wcpayPaymentRequest.unblockPaymentRequestButton(
										prButton
									);
								} );
							}
						}
					);
				} )
			);
		},

		attachCartPageEventListeners: ( prButton, paymentRequest ) => {
			if (
				( ! wcpayPaymentRequestParams.button.is_custom ||
					! wcpayPaymentRequest.isCustomPaymentRequestButton(
						prButton
					) ) &&
				( ! wcpayPaymentRequestParams.button.is_branded ||
					! wcpayPaymentRequest.isBrandedPaymentRequestButton(
						prButton
					) )
			) {
				return;
			}

			prButton.on( 'click', ( evt ) => {
				evt.preventDefault();
				paymentRequest.show();
			} );
		},

		showPaymentRequestButton: ( prButton ) => {
			if (
				wcpayPaymentRequest.isCustomPaymentRequestButton( prButton )
			) {
				prButton.addClass( 'is-active' );
				$(
					'#wcpay-payment-request-wrapper, #wcpay-payment-request-button-separator'
				).show();
			} else if (
				wcpayPaymentRequest.isBrandedPaymentRequestButton( prButton )
			) {
				$(
					'#wcpay-payment-request-wrapper, #wcpay-payment-request-button-separator'
				).show();
				$( '#wcpay-payment-request-button' ).html( prButton );
			} else if ( $( '#wcpay-payment-request-button' ).length ) {
				$(
					'#wcpay-payment-request-wrapper, #wcpay-payment-request-button-separator'
				).show();
				prButton.mount( '#wcpay-payment-request-button' );
			}
		},

		blockPaymentRequestButton: ( prButton ) => {
			// check if element isn't already blocked before calling block() to avoid blinking overlay issues
			// blockUI.isBlocked is either undefined or 0 when element is not blocked
			if (
				$( '#wcpay-payment-request-button' ).data( 'blockUI.isBlocked' )
			) {
				return;
			}

			$( '#wcpay-payment-request-button' ).block( { message: null } );
			if (
				wcpayPaymentRequest.isCustomPaymentRequestButton( prButton )
			) {
				prButton.addClass( 'is-blocked' );
			}
		},

		unblockPaymentRequestButton: ( prButton ) => {
			$( '#wcpay-payment-request-button' ).unblock();
			if (
				wcpayPaymentRequest.isCustomPaymentRequestButton( prButton )
			) {
				prButton.removeClass( 'is-blocked' );
			}
		},

		/**
		 * Initialize event handlers and UI state
		 */
		init: () => {
			if ( wcpayPaymentRequestParams.is_product_page ) {
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

	function setBackgroundImageWithFallback( element, background, fallback ) {
		element.css( 'background-image', 'url(' + background + ')' );
		// Need to use an img element to avoid CORS issues
		const testImg = document.createElement( 'img' );
		testImg.onerror = () => {
			element.css( 'background-image', 'url(' + fallback + ')' );
		};
		testImg.src = background;
	}
} );
