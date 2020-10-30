/* global wcpay_payment_request_params, Stripe */
jQuery( function( $ ) {
	'use strict';

	var stripe = Stripe( wcpay_payment_request_params.stripe.publishableKey, {
		stripeAccount: wcpay_payment_request_params.stripe.accountId,
	} );
	var paymentRequestType;

	/**
	 * Object to handle Stripe payment forms.
	 */
	var wcpay_payment_request = {
		/**
		 * Get WC AJAX endpoint URL.
		 *
		 * @param  {String} endpoint Endpoint.
		 * @return {String}
		 */
		getAjaxURL: function( endpoint ) {
			return wcpay_payment_request_params.ajax_url
				.toString()
				.replace( '%%endpoint%%', 'wcpay_' + endpoint );
		},

		getCartDetails: function() {
			var data = {
				security: wcpay_payment_request_params.nonce.payment
			};

			$.ajax( {
				type:    'POST',
				data:    data,
				url:     wcpay_payment_request.getAjaxURL( 'get_cart_details' ),
				success: function( response ) {
					wcpay_payment_request.startPaymentRequest( response );
				}
			} );
		},

		getAttributes: function() {
			var select = $( '.variations_form' ).find( '.variations select' ),
				data   = {},
				count  = 0,
				chosen = 0;

			select.each( function() {
				var attribute_name = $( this ).data( 'attribute_name' ) || $( this ).attr( 'name' );
				var value          = $( this ).val() || '';

				if ( value.length > 0 ) {
					chosen ++;
				}

				count ++;
				data[ attribute_name ] = value;
			});

			return {
				'count'      : count,
				'chosenCount': chosen,
				'data'       : data
			};
		},

		processPaymentMethod: function( paymentMethod, paymentRequestType ) {
			var data = wcpay_payment_request.getOrderData( paymentMethod, paymentRequestType );

			return $.ajax( {
				type:    'POST',
				data:    data,
				dataType: 'json',
				url:     wcpay_payment_request.getAjaxURL( 'create_order' )
			} );
		},

		/**
		 * Get order data.
		 *
		 * @since 3.1.0
		 * @version 4.0.0
		 * @param {PaymentResponse} source Payment Response instance.
		 *
		 * @return {Object}
		 */
		getOrderData: function( evt, paymentRequestType ) {
			var paymentMethod = evt.paymentMethod;
			var email         = paymentMethod.billing_details.email;
			var phone         = paymentMethod.billing_details.phone;
			var billing       = paymentMethod.billing_details.address;
			var name          = paymentMethod.billing_details.name;
			var shipping      = evt.shippingAddress;
			var data          = {
				_wpnonce:                  wcpay_payment_request_params.nonce.checkout,
				billing_first_name:        null !== name ? name.split( ' ' ).slice( 0, 1 ).join( ' ' ) : '',
				billing_last_name:         null !== name ? name.split( ' ' ).slice( 1 ).join( ' ' ) : '',
				billing_company:           '',
				billing_email:             null !== email   ? email : evt.payerEmail,
				billing_phone:             null !== phone   ? phone : evt.payerPhone.replace( '/[() -]/g', '' ),
				billing_country:           null !== billing ? billing.country : '',
				billing_address_1:         null !== billing ? billing.line1 : '',
				billing_address_2:         null !== billing ? billing.line2 : '',
				billing_city:              null !== billing ? billing.city : '',
				billing_state:             null !== billing ? billing.state : '',
				billing_postcode:          null !== billing ? billing.postal_code : '',
				shipping_first_name:       '',
				shipping_last_name:        '',
				shipping_company:          '',
				shipping_country:          '',
				shipping_address_1:        '',
				shipping_address_2:        '',
				shipping_city:             '',
				shipping_state:            '',
				shipping_postcode:         '',
				shipping_method:           [ null === evt.shippingOption ? null : evt.shippingOption.id ],
				order_comments:            '',
				payment_method:            'woocommerce_payments',
				ship_to_different_address: 1,
				terms:                     1,
				'wcpay-payment-method':    paymentMethod.id,
				payment_request_type:      paymentRequestType
			};

			if ( shipping ) {
				data.shipping_first_name = shipping.recipient.split( ' ' ).slice( 0, 1 ).join( ' ' );
				data.shipping_last_name  = shipping.recipient.split( ' ' ).slice( 1 ).join( ' ' );
				data.shipping_company    = shipping.organization;
				data.shipping_country    = shipping.country;
				data.shipping_address_1  = typeof shipping.addressLine[0] === 'undefined' ? '' : shipping.addressLine[0];
				data.shipping_address_2  = typeof shipping.addressLine[1] === 'undefined' ? '' : shipping.addressLine[1];
				data.shipping_city       = shipping.city;
				data.shipping_state      = shipping.region;
				data.shipping_postcode   = shipping.postalCode;
			}

			return data;
		},

		/**
		 * Generate error message HTML.
		 *
		 * @since 3.1.0
		 * @version 4.0.0
		 * @param  {String} message Error message.
		 * @return {Object}
		 */
		getErrorMessageHTML: function( message ) {
			return $( '<div class="woocommerce-error" />' ).text( message );
		},

		/**
		 * Abort payment and display error messages.
		 *
		 * @since 3.1.0
		 * @version 4.0.0
		 * @param {PaymentResponse} payment Payment response instance.
		 * @param {String}          message Error message to display.
		 */
		abortPayment: function( payment, message ) {
			payment.complete( 'fail' );

			$( '.woocommerce-error' ).remove();

			if ( wcpay_payment_request_params.is_product_page ) {
				var element = $( '.product' );

				element.before( message );

				$( 'html, body' ).animate({
					scrollTop: element.prev( '.woocommerce-error' ).offset().top
				}, 600 );
			} else {
				var $form = $( '.shop_table.cart' ).closest( 'form' );

				$form.before( message );

				$( 'html, body' ).animate({
					scrollTop: $form.prev( '.woocommerce-error' ).offset().top
				}, 600 );
			}
		},

		/**
		 * Complete payment.
		 *
		 * @since 3.1.0
		 * @version 4.0.0
		 * @param {PaymentResponse} payment Payment response instance.
		 * @param {String}          url     Order thank you page URL.
		 */
		completePayment: function( payment, url ) {
			wcpay_payment_request.block();

			payment.complete( 'success' );

			// Success, then redirect to the Thank You page.
			window.location = url;
		},

		block: function() {
			$.blockUI( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );
		},

		/**
		 * Update shipping options.
		 *
		 * @param {Object}         details Payment details.
		 * @param {PaymentAddress} address Shipping address.
		 */
		updateShippingOptions: function( details, address ) {
			var data = {
				security:  wcpay_payment_request_params.nonce.shipping,
				country:   address.country,
				state:     address.region,
				postcode:  address.postalCode,
				city:      address.city,
				address:   typeof address.addressLine[0] === 'undefined' ? '' : address.addressLine[0],
				address_2: typeof address.addressLine[1] === 'undefined' ? '' : address.addressLine[1],
				payment_request_type: paymentRequestType,
				is_product_page: wcpay_payment_request_params.is_product_page,
			};

			return $.ajax( {
				type:    'POST',
				data:    data,
				url:     wcpay_payment_request.getAjaxURL( 'get_shipping_options' )
			} );
		},

		/**
		 * Updates the shipping price and the total based on the shipping option.
		 *
		 * @param {Object}   details        The line items and shipping options.
		 * @param {String}   shippingOption User's preferred shipping option to use for shipping price calculations.
		 */
		updateShippingDetails: function( details, shippingOption ) {
			var data = {
				security: wcpay_payment_request_params.nonce.update_shipping,
				shipping_method: [ shippingOption.id ],
				payment_request_type: paymentRequestType,
				is_product_page: wcpay_payment_request_params.is_product_page,
			};

			return $.ajax( {
				type: 'POST',
				data: data,
				url:  wcpay_payment_request.getAjaxURL( 'update_shipping_method' )
			} );
		},

		/**
		 * Adds the item to the cart and return cart details.
		 *
		 */
		addToCart: function() {
			var product_id = $( '.single_add_to_cart_button' ).val();

			// Check if product is a variable product.
			if ( $( '.single_variation_wrap' ).length ) {
				product_id = $( '.single_variation_wrap' ).find( 'input[name="product_id"]' ).val();
			}

			var data = {
				security: wcpay_payment_request_params.nonce.add_to_cart,
				product_id: product_id,
				qty: $( '.quantity .qty' ).val(),
				attributes: $( '.variations_form' ).length ? wcpay_payment_request.getAttributes().data : []
			};

			// add addons data to the POST body
			var formData = $( 'form.cart' ).serializeArray();
			$.each( formData, function( i, field ) {
				if ( /^addon-/.test( field.name ) ) {
					if ( /\[\]$/.test( field.name ) ) {
						var fieldName = field.name.substring( 0, field.name.length - 2);
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
				url:  wcpay_payment_request.getAjaxURL( 'add_to_cart' )
			} );
		},

		clearCart: function() {
			var data = {
					'security': wcpay_payment_request_params.nonce.clear_cart
				};

			return $.ajax( {
				type:    'POST',
				data:    data,
				url:     wcpay_payment_request.getAjaxURL( 'clear_cart' ),
				success: function( response ) {}
			} );
		},

		getRequestOptionsFromLocal: function() {
			return {
				total: wcpay_payment_request_params.product.total,
				currency: wcpay_payment_request_params.checkout.currency_code,
				country: wcpay_payment_request_params.checkout.country_code,
				requestPayerName: true,
				requestPayerEmail: true,
				requestPayerPhone: wcpay_payment_request_params.checkout.needs_payer_phone,
				requestShipping: wcpay_payment_request_params.product.requestShipping,
				displayItems: wcpay_payment_request_params.product.displayItems
			};
		},

		/**
		 * Starts the payment request
		 *
		 * @since 4.0.0
		 * @version 4.0.0
		 */
		startPaymentRequest: function( cart ) {
			var paymentDetails,
				options;

			if ( wcpay_payment_request_params.is_product_page ) {
				options = wcpay_payment_request.getRequestOptionsFromLocal();

				paymentDetails = options;
			} else {
				options = {
					total: cart.order_data.total,
					currency: cart.order_data.currency,
					country: cart.order_data.country_code,
					requestPayerName: true,
					requestPayerEmail: true,
					requestPayerPhone: wcpay_payment_request_params.checkout.needs_payer_phone,
					requestShipping: cart.shipping_required ? true : false,
					displayItems: cart.order_data.displayItems
				};

				paymentDetails = cart.order_data;
			}

			var paymentRequest = stripe.paymentRequest( options );

			var elements = stripe.elements( { locale: wcpay_payment_request_params.button.locale } );
			var prButton = wcpay_payment_request.createPaymentRequestButton( elements, paymentRequest );

			// Check the availability of the Payment Request API first.
			paymentRequest.canMakePayment().then( function( result ) {
				if ( ! result ) {
					return;
				}
				paymentRequestType = result.applePay ? 'apple_pay' : 'payment_request_api';
				wcpay_payment_request.attachPaymentRequestButtonEventListeners( prButton, paymentRequest );
				wcpay_payment_request.showPaymentRequestButton( prButton );
			} );

			// Possible statuses success, fail, invalid_payer_name, invalid_payer_email, invalid_payer_phone, invalid_shipping_address.
			paymentRequest.on( 'shippingaddresschange', function( evt ) {
				$.when( wcpay_payment_request.updateShippingOptions( paymentDetails, evt.shippingAddress ) ).then( function( response ) {
					evt.updateWith( { status: response.result, shippingOptions: response.shipping_options, total: response.total, displayItems: response.displayItems } );
				} );
			} );

			paymentRequest.on( 'shippingoptionchange', function( evt ) {
				$.when( wcpay_payment_request.updateShippingDetails( paymentDetails, evt.shippingOption ) ).then( function( response ) {
					if ( 'success' === response.result ) {
						evt.updateWith( { status: 'success', total: response.total, displayItems: response.displayItems } );
					}

					if ( 'fail' === response.result ) {
						evt.updateWith( { status: 'fail' } );
					}
				} );
			} );

			paymentRequest.on( 'paymentmethod', function( evt ) {
				// Check if we allow prepaid cards.
				if ( 'no' === wcpay_payment_request_params.stripe.allow_prepaid_card && 'prepaid' === evt.source.card.funding ) {
					wcpay_payment_request.abortPayment( evt, wcpay_payment_request.getErrorMessageHTML( wcpay_payment_request_params.i18n.no_prepaid_card ) );
				} else {
					$.when( wcpay_payment_request.processPaymentMethod( evt, paymentRequestType ) ).then( function( response ) {
						if ( 'success' === response.result ) {
							wcpay_payment_request.completePayment( evt, response.redirect );
						} else {
							wcpay_payment_request.abortPayment( evt, response.messages );
						}
					} );
				}
			} );
		},

		getSelectedProductData: function() {
			var product_id = $( '.single_add_to_cart_button' ).val();

			// Check if product is a variable product.
			if ( $( '.single_variation_wrap' ).length ) {
				product_id = $( '.single_variation_wrap' ).find( 'input[name="product_id"]' ).val();
			}

			var addons = $( '#product-addons-total' ).data('price_data') || [];
			var addon_value = addons.reduce( function ( sum, addon ) { return sum + addon.cost; }, 0 );

			var data = {
				security: wcpay_payment_request_params.nonce.get_selected_product_data,
				product_id: product_id,
				qty: $( '.quantity .qty' ).val(),
				attributes: $( '.variations_form' ).length ? wcpay_payment_request.getAttributes().data : [],
				addon_value: addon_value,
			};

			return $.ajax( {
				type: 'POST',
				data: data,
				url:  wcpay_payment_request.getAjaxURL( 'get_selected_product_data' )
			} );
		},


		/**
		 * Creates a wrapper around a function that ensures a function can not
		 * called in rappid succesion. The function can only be executed once and then agin after
		 * the wait time has expired.  Even if the wrapper is called multiple times, the wrapped
		 * function only excecutes once and then blocks until the wait time expires.
		 *
		 * @param {int} wait       Milliseconds wait for the next time a function can be executed.
		 * @param {function} func       The function to be wrapped.
		 * @param {bool} immediate Overriding the wait time, will force the function to fire everytime.
		 *
		 * @return {function} A wrapped function with execution limited by the wait time.
		 */
		debounce: function( wait, func, immediate ) {
			var timeout;
			return function() {
				var context = this, args = arguments;
				var later = function() {
					timeout = null;
					if (!immediate) func.apply(context, args);
				};
				var callNow = immediate && !timeout;
				clearTimeout(timeout);
				timeout = setTimeout(later, wait);
				if (callNow) func.apply(context, args);
			};
		},

		/**
		 * Creates stripe paymentRequest element or connects to custom button
		 *
		 * @param {object} elements       Stripe elements instance.
		 * @param {object} paymentRequest Stripe paymentRequest object.
		 *
		 * @return {object} Stripe paymentRequest element or custom button jQuery element.
		 */
		createPaymentRequestButton: function( elements, paymentRequest ) {
			var button;
			if ( wcpay_payment_request_params.button.is_custom ) {
				button = $( wcpay_payment_request_params.button.css_selector );
				if ( button.length ) {
					// We fallback to default paymentRequest button if no custom button is found in the UI.
					// Add flag to be sure that created button is custom button rather than fallback element.
					button.data( 'isCustom', true );
					return button;
				}
			}

			if ( wcpay_payment_request_params.button.is_branded ) {
				if ( wcpay_payment_request.shouldUseGooglePayBrand() ) {
					button = wcpay_payment_request.createGooglePayButton();
					// Add flag to be sure that created button is branded rather than fallback element.
					button.data( 'isBranded', true );
					return button;
				} else {
					// Not implemented branded buttons default to Stripe's button
					// Apple Pay buttons can also fall back to Stripe's button, as it's already branded
					// Set button type to default or buy, depending on branded type, to avoid issues with Stripe
					wcpay_payment_request_params.button.type = 'long' === wcpay_payment_request_params.button.branded_type ? 'buy' : 'default';
				}
			}

			return elements.create( 'paymentRequestButton', {
				paymentRequest: paymentRequest,
				style: {
					paymentRequestButton: {
						type: wcpay_payment_request_params.button.type,
						theme: wcpay_payment_request_params.button.theme,
						height: wcpay_payment_request_params.button.height + 'px',
					},
				},
			} );
		},

		/**
		 * Checks if button is custom payment request button.
		 *
		 * @param {object} prButton Stripe paymentRequest element or custom jQuery element.
		 *
		 * @return {boolean} True when prButton is custom button jQuery element.
		 */
		isCustomPaymentRequestButton: function ( prButton ) {
			return prButton && 'function' === typeof prButton.data && prButton.data( 'isCustom' );
		},

		isBrandedPaymentRequestButton: function ( prButton ) {
			return prButton && 'function' === typeof prButton.data && prButton.data( 'isBranded' );
		},

		shouldUseGooglePayBrand: function () {
			var ua = window.navigator.userAgent.toLowerCase();
			var isChrome = /chrome/.test( ua ) && ! /edge|edg|opr|brave\//.test( ua ) && 'Google Inc.' === window.navigator.vendor;
			// newer versions of Brave do not have the userAgent string
			var isBrave = isChrome && window.navigator.brave;
			return isChrome && ! isBrave;
		},

		createGooglePayButton: function () {
			var allowedThemes = [ 'dark', 'light' ];
			var allowedTypes = [ 'short', 'long' ];

			var theme  = wcpay_payment_request_params.button.theme;
			var type   = wcpay_payment_request_params.button.branded_type;
			var locale = wcpay_payment_request_params.button.locale;
			var height = wcpay_payment_request_params.button.height;
			theme = allowedThemes.includes( theme ) ? theme : 'light';
			type = allowedTypes.includes( type ) ? type : 'long';

			var button = $( '<button type="button" id="wcpay-branded-button" aria-label="Google Pay" class="gpay-button"></button>' );
			button.css( 'height', height + 'px' );
			button.addClass( theme + ' ' + type );
			if ( 'long' === type ) {
				var url = 'https://www.gstatic.com/instantbuy/svg/' + theme + '/' + locale + '.svg';
				var fallbackUrl = 'https://www.gstatic.com/instantbuy/svg/' + theme + '/en.svg';
				// Check if locale GPay button exists, default to en if not
				setBackgroundImageWithFallback( button, url, fallbackUrl );
			}

			return button;
		},

		attachPaymentRequestButtonEventListeners: function( prButton, paymentRequest ) {
			if ( wcpay_payment_request_params.is_product_page ) {
				wcpay_payment_request.attachProductPageEventListeners( prButton, paymentRequest );
			} else {
				wcpay_payment_request.attachCartPageEventListeners( prButton, paymentRequest );
			}
		},

		attachProductPageEventListeners: function( prButton, paymentRequest ) {
			var paymentRequestError = [];
			var addToCartButton = $( '.single_add_to_cart_button' );

			prButton.on( 'click', function ( evt ) {
				// First check if product can be added to cart.
				if ( addToCartButton.is( '.disabled' ) ) {
					evt.preventDefault(); // Prevent showing payment request modal.
					if ( addToCartButton.is( '.wc-variation-is-unavailable' ) ) {
						window.alert( wc_add_to_cart_variation_params.i18n_unavailable_text );
					} else if ( addToCartButton.is( '.wc-variation-selection-needed' ) ) {
						window.alert( wc_add_to_cart_variation_params.i18n_make_a_selection_text );
					}
					return;
				}

				if ( 0 < paymentRequestError.length ) {
					evt.preventDefault();
					window.alert( paymentRequestError );
					return;
				}

				wcpay_payment_request.addToCart();

				if ( wcpay_payment_request.isCustomPaymentRequestButton( prButton ) || wcpay_payment_request.isBrandedPaymentRequestButton( prButton ) ) {
					evt.preventDefault();
					paymentRequest.show();
				}
			});

			$( document.body ).on( 'woocommerce_variation_has_changed', function () {
				wcpay_payment_request.blockPaymentRequestButton( prButton );

				$.when( wcpay_payment_request.getSelectedProductData() ).then( function ( response ) {
					$.when(
						paymentRequest.update( {
							total: response.total,
							displayItems: response.displayItems,
						} )
					).then( function () {
						wcpay_payment_request.unblockPaymentRequestButton( prButton );
					} );
				});
			} );

			// Block the payment request button as soon as an "input" event is fired, to avoid sync issues
			// when the customer clicks on the button before the debounced event is processed.
			$( '.quantity' ).on( 'input', '.qty', function() {
				wcpay_payment_request.blockPaymentRequestButton( prButton );
			} );

			$( '.quantity' ).on( 'input', '.qty', wcpay_payment_request.debounce( 250, function() {
				wcpay_payment_request.blockPaymentRequestButton( prButton );
				paymentRequestError = [];

				$.when( wcpay_payment_request.getSelectedProductData() ).then( function ( response ) {
					if ( response.error ) {
						paymentRequestError = [ response.error ];
						wcpay_payment_request.unblockPaymentRequestButton( prButton );
					} else {
						$.when(
							paymentRequest.update( {
								total: response.total,
								displayItems: response.displayItems,
							} )
						).then( function () {
							wcpay_payment_request.unblockPaymentRequestButton( prButton );
						});
					}
				} );
			}));
		},

		attachCartPageEventListeners: function ( prButton, paymentRequest ) {
			if ( ( ! wcpay_payment_request_params.button.is_custom || ! wcpay_payment_request.isCustomPaymentRequestButton( prButton ) ) &&
				( ! wcpay_payment_request_params.button.is_branded || ! wcpay_payment_request.isBrandedPaymentRequestButton( prButton ) ) ) {
				return;
			}

			prButton.on( 'click', function ( evt ) {
				evt.preventDefault();
				paymentRequest.show();
			} );
		},

		showPaymentRequestButton: function( prButton ) {
			if ( wcpay_payment_request.isCustomPaymentRequestButton( prButton ) ) {
				prButton.addClass( 'is-active' );
				$( '#wcpay-payment-request-wrapper, #wcpay-payment-request-button-separator' ).show();
			} else if ( wcpay_payment_request.isBrandedPaymentRequestButton( prButton ) ) {
				$( '#wcpay-payment-request-wrapper, #wcpay-payment-request-button-separator' ).show();
				$( '#wcpay-payment-request-button' ).html( prButton );
			} else if ( $( '#wcpay-payment-request-button' ).length ) {
				$( '#wcpay-payment-request-wrapper, #wcpay-payment-request-button-separator' ).show();
				prButton.mount( '#wcpay-payment-request-button' );
			}
		},

		blockPaymentRequestButton: function( prButton ) {
			// check if element isn't already blocked before calling block() to avoid blinking overlay issues
			// blockUI.isBlocked is either undefined or 0 when element is not blocked
			if ( $( '#wcpay-payment-request-button' ).data( 'blockUI.isBlocked' ) ) {
				return;
			}

			$( '#wcpay-payment-request-button' ).block( { message: null } );
			if ( wcpay_payment_request.isCustomPaymentRequestButton( prButton ) ) {
				prButton.addClass( 'is-blocked' );
			}
		},

		unblockPaymentRequestButton: function( prButton ) {
			$( '#wcpay-payment-request-button' ).unblock();
			if ( wcpay_payment_request.isCustomPaymentRequestButton( prButton ) ) {
				prButton.removeClass( 'is-blocked' );
			}
		},

		/**
		 * Initialize event handlers and UI state
		 *
		 * @since 4.0.0
		 * @version 4.0.0
		 */
		init: function() {
			if ( wcpay_payment_request_params.is_product_page ) {
				wcpay_payment_request.startPaymentRequest( '' );
			} else {
				wcpay_payment_request.getCartDetails();
			}

		},
	};

	wcpay_payment_request.init();

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_cart_totals', function() {
		wcpay_payment_request.init();
	} );

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_checkout', function() {
		wcpay_payment_request.init();
	} );

	function setBackgroundImageWithFallback( element, background, fallback ) {
		element.css( 'background-image', 'url(' + background + ')' );
		// Need to use an img element to avoid CORS issues
		var testImg = document.createElement("img");
		testImg.onerror = function () {
			element.css( 'background-image', 'url(' + fallback + ')' );
		}
		testImg.src = background;
	}
} );
