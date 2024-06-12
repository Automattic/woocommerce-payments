/* global jQuery, wcpayExpressCheckoutParams */

/**
 * Internal dependencies
 */
import WCPayAPI from '../checkout/api';
import '../checkout/express-checkout-buttons.scss';

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

	/**
	 * Object to handle Stripe payment forms.
	 */
	const wcpayECE = {
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
			wcpayECE.block();
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
					? wcpayECE.getAttributes().data
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
		 * Starts the Express Checkout Element
		 *
		 * @param {Object} options ECE options.
		 */
		startExpressCheckoutElement: ( options ) => {
			const elements = api.getStripe().elements( {
				mode: options?.mode ?? 'payment',
				amount: options?.total,
				currency: options?.currency,
			} );

			const eceButton = wcpayECE.createButton( elements, {
				buttonType: {
					googlePay: wcpayExpressCheckoutParams.button.type,
					applePay: wcpayExpressCheckoutParams.button.type,
				},
			} );

			wcpayECE.showButton( eceButton );

			wcpayECE.attachButtonEventListeners( eceButton );

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
				wcpayECE.paymentAborted = true;
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
					? wcpayECE.getAttributes().data
					: [],
				addon_value: addonValue,
				...depositObject,
			};

			return api.paymentRequestGetSelectedProductData( data );
		},

		/**
		 * Creates Stripe Express Checkout Element.
		 *
		 * @param {Object} elements       Stripe elements instance.
		 * @param {Object} options 		  Options for creating the Express Checkout Element.
		 *
		 * @return {Object} Stripe Express Checkout Element.
		 */
		createButton: ( elements, options ) => {
			return elements.create( 'expressCheckout', options );
		},

		getElements: () => {
			return $(
				'.wcpay-payment-request-wrapper,#wcpay-express-checkout-button-separator'
			);
		},

		hide: () => {
			wcpayECE.getElements().hide();
		},

		show: () => {
			wcpayECE.getElements().show();
		},

		showButton: ( eceButton ) => {
			if ( $( '#wcpay-express-checkout-element' ).length ) {
				wcpayECE.show();
				eceButton.mount( '#wcpay-express-checkout-element' );
			}
		},

		blockButton: () => {
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

		unblockButton: () => {
			wcpayECE.show();
			$( '#wcpay-express-checkout-button' ).unblock();
		},

		/**
		 * Initialize event handlers and UI state
		 */
		init: () => {
			if ( wcpayExpressCheckoutParams.is_pay_for_order ) {
				if ( ! window.wcpayECEPayForOrderParams ) {
					return;
				}

				wcpayECE.startExpressCheckoutElement();
			} else if ( wcpayExpressCheckoutParams.is_product_page ) {
				wcpayECE.startExpressCheckoutElement( {
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
				// cart details.
				api.paymentRequestGetCartDetails().then( ( cart ) => {
					wcpayECE.startExpressCheckoutElement( {
						mode: 'payment',
						total: 1000,
						currency: 'usd',
						requestShipping: cart.needs_shipping,
						displayItems: cart.displayItems,
					} );
				} );
			}

			// After initializing a new element, we need to reset the paymentAborted flag.
			wcpayECE.paymentAborted = false;
		},
	};

	// We don't need to initialize ECE on the checkout page now because it will be initialized by updated_checkout event.
	if (
		! wcpayExpressCheckoutParams.is_checkout_page ||
		wcpayExpressCheckoutParams.is_pay_for_order
	) {
		wcpayECE.init();
	}

	// We need to refresh ECE data when total is updated.
	$( document.body ).on( 'updated_cart_totals', () => {
		wcpayECE.init();
	} );

	// We need to refresh ECE data when total is updated.
	$( document.body ).on( 'updated_checkout', () => {
		wcpayECE.init();
	} );
} );
