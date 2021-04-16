/* global wcpayPaymentRequestParams, Stripe, jQuery, wc_add_to_cart_variation_params */

/**
 * Internal dependencies
 */
import './style.scss';

jQuery( ( $ ) => {
	// Don't load if blocks checkout is being loaded.
	if ( wcpayPaymentRequestParams.has_block ) {
		return;
	}

	const stripe = Stripe( wcpayPaymentRequestParams.stripe.publishableKey, {
		stripeAccount: wcpayPaymentRequestParams.stripe.accountId,
	} );
	let paymentRequestType;

	/**
	 * Object to handle Stripe payment forms.
	 */
	const wcpayPaymentRequest = {
		/**
		 * Get WC AJAX endpoint URL.
		 *
		 * @param  {string} endpoint Endpoint.
		 * @return {string} URL with interpolated endpoint.
		 */
		getAjaxURL: ( endpoint ) =>
			wcpayPaymentRequestParams.ajax_url
				.toString()
				.replace( '%%endpoint%%', 'wcpay_' + endpoint ),

		getCartDetails: () => {
			const data = {
				security: wcpayPaymentRequestParams.nonce.payment,
			};

			$.ajax( {
				type: 'POST',
				data: data,
				url: wcpayPaymentRequest.getAjaxURL( 'get_cart_details' ),
				success: ( response ) => {
					wcpayPaymentRequest.startPaymentRequest( response );
				},
			} );
		},

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

		processPaymentMethod: ( paymentMethod ) => {
			const data = wcpayPaymentRequest.getOrderData( paymentMethod );

			return $.ajax( {
				type: 'POST',
				data: data,
				dataType: 'json',
				url: wcpayPaymentRequest.getAjaxURL( 'create_order' ),
			} );
		},

		/**
		 * Get order data.
		 *
		 * @param {Object} evt Event.
		 * @return {Object} Order data.
		 */
		// - TODO: Replace this normalization function with the same from blocks
		getOrderData: ( evt ) => {
			/* eslint-disable camelcase */
			const paymentMethod = evt.paymentMethod;
			const email = paymentMethod.billing_details.email;
			const phone = paymentMethod.billing_details.phone;
			const billing = paymentMethod.billing_details.address;
			const name = paymentMethod.billing_details.name;
			const shipping = evt.shippingAddress;
			const data = {
				_wpnonce: wcpayPaymentRequestParams.nonce.checkout,
				billing_first_name:
					null !== name
						? name.split( ' ' ).slice( 0, 1 ).join( ' ' )
						: '',
				billing_last_name:
					null !== name
						? name.split( ' ' ).slice( 1 ).join( ' ' )
						: '',
				billing_company: '',
				billing_email: null !== email ? email : evt.payerEmail,
				billing_phone:
					null !== phone
						? phone
						: evt.payerPhone.replace( '/[() -]/g', '' ),
				billing_country: null !== billing ? billing.country : '',
				billing_address_1: null !== billing ? billing.line1 : '',
				billing_address_2: null !== billing ? billing.line2 : '',
				billing_city: null !== billing ? billing.city : '',
				billing_state: null !== billing ? billing.state : '',
				billing_postcode: null !== billing ? billing.postal_code : '',
				shipping_first_name: '',
				shipping_last_name: '',
				shipping_company: '',
				shipping_country: '',
				shipping_address_1: '',
				shipping_address_2: '',
				shipping_city: '',
				shipping_state: '',
				shipping_postcode: '',
				shipping_method: [
					null === evt.shippingOption ? null : evt.shippingOption.id,
				],
				order_comments: '',
				payment_method: 'woocommerce_payments',
				ship_to_different_address: 1,
				terms: 1,
				'wcpay-payment-method': paymentMethod.id,
				payment_request_type: paymentRequestType,
			};

			if ( shipping ) {
				data.shipping_first_name = shipping.recipient
					.split( ' ' )
					.slice( 0, 1 )
					.join( ' ' );
				data.shipping_last_name = shipping.recipient
					.split( ' ' )
					.slice( 1 )
					.join( ' ' );
				data.shipping_company = shipping.organization;
				data.shipping_country = shipping.country;
				data.shipping_address_1 =
					'undefined' === typeof shipping.addressLine[ 0 ]
						? ''
						: shipping.addressLine[ 0 ];
				data.shipping_address_2 =
					'undefined' === typeof shipping.addressLine[ 1 ]
						? ''
						: shipping.addressLine[ 1 ];
				data.shipping_city = shipping.city;
				data.shipping_state = shipping.region;
				data.shipping_postcode = shipping.postalCode;
			}

			return data;
			/* eslint-enable camelcase */
		},

		/**
		 * Generate error message HTML.
		 *
		 * @param  {string} message Error message.
		 * @return {Object} Error message HTML.
		 */
		getErrorMessageHTML: ( message ) =>
			$( '<div class="woocommerce-error" />' ).text( message ),

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
				$container.append( message );

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
		 * @param {PaymentResponse} payment Payment response instance.
		 * @param {string}          url     Order thank you page URL.
		 */
		completePayment: ( payment, url ) => {
			wcpayPaymentRequest.block();

			payment.complete( 'success' );

			// Success, then redirect to the Thank You page.
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
		 * Update shipping options.
		 *
		 * @param {Object}         details Payment details.
		 * @param {PaymentAddress} address Shipping address.
		 * @return {Object} AJAX request.
		 */
		updateShippingOptions: ( details, address ) => {
			// - TODO: Update data below with normalization function from blocks
			/* eslint-disable camelcase */
			const data = {
				security: wcpayPaymentRequestParams.nonce.shipping,
				country: address.country,
				state: address.region,
				postcode: address.postalCode,
				city: address.city,
				address_1:
					'undefined' === typeof address.addressLine[ 0 ]
						? ''
						: address.addressLine[ 0 ],
				address_2:
					'undefined' === typeof address.addressLine[ 1 ]
						? ''
						: address.addressLine[ 1 ],
				payment_request_type: paymentRequestType,
				is_product_page: wcpayPaymentRequestParams.is_product_page,
			};
			/* eslint-enable camelcase */

			return $.ajax( {
				type: 'POST',
				data: data,
				url: wcpayPaymentRequest.getAjaxURL( 'get_shipping_options' ),
			} );
		},

		/**
		 * Updates the shipping price and the total based on the shipping option.
		 *
		 * @param {Object}   details        The line items and shipping options.
		 * @param {string}   shippingOption User's preferred shipping option to use for shipping price calculations.
		 * @return {Object} AJAX request.
		 */
		updateShippingDetails: ( details, shippingOption ) => {
			/* eslint-disable camelcase */
			const data = {
				security: wcpayPaymentRequestParams.nonce.update_shipping,
				shipping_method: [ shippingOption.id ],
				payment_request_type: paymentRequestType,
				is_product_page: wcpayPaymentRequestParams.is_product_page,
			};
			/* eslint-enable camelcase */

			return $.ajax( {
				type: 'POST',
				data: data,
				url: wcpayPaymentRequest.getAjaxURL( 'update_shipping_method' ),
			} );
		},

		/**
		 * Adds the item to the cart and return cart details.
		 *
		 * @return {Object} AJAX request.
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
				security: wcpayPaymentRequestParams.nonce.add_to_cart,
				// eslint-disable-next-line camelcase
				product_id: productId,
				qty: $( '.quantity .qty' ).val(),
				attributes: $( '.variations_form' ).length
					? wcpayPaymentRequest.getAttributes().data
					: [],
			};

			// add addons data to the POST body
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

			return $.ajax( {
				type: 'POST',
				data: data,
				url: wcpayPaymentRequest.getAjaxURL( 'add_to_cart' ),
			} );
		},

		clearCart: () => {
			const data = {
				security: wcpayPaymentRequestParams.nonce.clear_cart,
			};

			return $.ajax( {
				type: 'POST',
				data: data,
				url: wcpayPaymentRequest.getAjaxURL( 'clear_cart' ),
				success: () => {},
			} );
		},

		getRequestOptionsFromLocal: () => {
			return {
				total: wcpayPaymentRequestParams.product.total,
				currency: wcpayPaymentRequestParams.checkout.currency_code,
				country: wcpayPaymentRequestParams.checkout.country_code,
				requestPayerName: true,
				requestPayerEmail: true,
				requestPayerPhone:
					wcpayPaymentRequestParams.checkout.needs_payer_phone,
				requestShipping:
					wcpayPaymentRequestParams.product.requestShipping,
				displayItems: wcpayPaymentRequestParams.product.displayItems,
			};
		},

		/**
		 * Starts the payment request
		 *
		 * @param {Object} cart Cart data.
		 */
		startPaymentRequest: ( cart ) => {
			let paymentDetails, options;

			if ( wcpayPaymentRequestParams.is_product_page ) {
				options = wcpayPaymentRequest.getRequestOptionsFromLocal();

				paymentDetails = options;
			} else {
				options = {
					total: cart.order_data.total,
					currency: cart.order_data.currency,
					country: cart.order_data.country_code,
					requestPayerName: true,
					requestPayerEmail: true,
					requestPayerPhone:
						wcpayPaymentRequestParams.checkout.needs_payer_phone,
					requestShipping: cart.shipping_required ? true : false,
					displayItems: cart.order_data.displayItems,
				};

				paymentDetails = cart.order_data;
			}

			// Puerto Rico (PR) is the only US territory/possession that's supported by Stripe.
			// Since it's considered a US state by Stripe, we need to do some special mapping.
			if ( 'PR' === options.country ) {
				options.country = 'US';
			}

			const paymentRequest = stripe.paymentRequest( options );

			const elements = stripe.elements( {
				locale: wcpayPaymentRequestParams.button.locale,
			} );
			const prButton = wcpayPaymentRequest.createPaymentRequestButton(
				elements,
				paymentRequest
			);

			// Check the availability of the Payment Request API first.
			paymentRequest.canMakePayment().then( ( result ) => {
				if ( ! result ) {
					return;
				}
				paymentRequestType = result.applePay
					? 'apple_pay'
					: 'payment_request_api';
				wcpayPaymentRequest.attachPaymentRequestButtonEventListeners(
					prButton,
					paymentRequest
				);
				wcpayPaymentRequest.showPaymentRequestButton( prButton );
			} );

			// Possible statuses success, fail, invalid_payer_name, invalid_payer_email, invalid_payer_phone, invalid_shipping_address.
			paymentRequest.on( 'shippingaddresschange', ( evt ) => {
				$.when(
					wcpayPaymentRequest.updateShippingOptions(
						paymentDetails,
						evt.shippingAddress
					)
				).then( ( response ) => {
					evt.updateWith( {
						status: response.result,
						shippingOptions: response.shipping_options,
						total: response.total,
						displayItems: response.displayItems,
					} );
				} );
			} );

			paymentRequest.on( 'shippingoptionchange', ( evt ) => {
				$.when(
					wcpayPaymentRequest.updateShippingDetails(
						paymentDetails,
						evt.shippingOption
					)
				).then( ( response ) => {
					if ( 'success' === response.result ) {
						evt.updateWith( {
							status: 'success',
							total: response.total,
							displayItems: response.displayItems,
						} );
					}

					if ( 'fail' === response.result ) {
						evt.updateWith( { status: 'fail' } );
					}
				} );
			} );

			paymentRequest.on( 'paymentmethod', ( evt ) => {
				// Check if we allow prepaid cards.
				if (
					'no' ===
						wcpayPaymentRequestParams.stripe.allow_prepaid_card &&
					'prepaid' === evt.source.card.funding
				) {
					wcpayPaymentRequest.abortPayment(
						evt,
						wcpayPaymentRequest.getErrorMessageHTML(
							wcpayPaymentRequestParams.i18n.no_prepaid_card
						)
					);
				} else {
					$.when(
						wcpayPaymentRequest.processPaymentMethod( evt )
					).then( ( response ) => {
						if ( 'success' === response.result ) {
							wcpayPaymentRequest.completePayment(
								evt,
								response.redirect
							);
						} else {
							wcpayPaymentRequest.abortPayment(
								evt,
								response.messages
							);
						}
					} );
				}
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
				security:
					wcpayPaymentRequestParams.nonce.get_selected_product_data,
				// eslint-disable-next-line camelcase
				product_id: productId,
				qty: $( '.quantity .qty' ).val(),
				attributes: $( '.variations_form' ).length
					? wcpayPaymentRequest.getAttributes().data
					: [],
				// eslint-disable-next-line camelcase
				addon_value: addonValue,
			};

			return $.ajax( {
				type: 'POST',
				data: data,
				url: wcpayPaymentRequest.getAjaxURL(
					'get_selected_product_data'
				),
			} );
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
				if ( wcpayPaymentRequest.shouldUseGooglePayBrand() ) {
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

		shouldUseGooglePayBrand: () => {
			const ua = window.navigator.userAgent.toLowerCase();
			const isChrome =
				/chrome/.test( ua ) &&
				! /edge|edg|opr|brave\//.test( ua ) &&
				'Google Inc.' === window.navigator.vendor;
			// newer versions of Brave do not have the userAgent string
			const isBrave = isChrome && window.navigator.brave;
			return isChrome && ! isBrave;
		},

		createGooglePayButton: () => {
			const allowedThemes = [ 'dark', 'light' ];
			const allowedTypes = [ 'short', 'long' ];

			let theme = wcpayPaymentRequestParams.button.theme;
			let type = wcpayPaymentRequestParams.button.branded_type;
			const locale = wcpayPaymentRequestParams.button.locale;
			const height = wcpayPaymentRequestParams.button.height;
			theme = allowedThemes.includes( theme ) ? theme : 'light';
			type = allowedTypes.includes( type ) ? type : 'long';

			const button = $(
				'<button type="button" id="wcpay-branded-button" aria-label="Google Pay" class="gpay-button"></button>'
			);
			button.css( 'height', height + 'px' );
			button.addClass( theme + ' ' + type );
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
				wcpayPaymentRequest.startPaymentRequest( '' );
			} else {
				wcpayPaymentRequest.getCartDetails();
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
