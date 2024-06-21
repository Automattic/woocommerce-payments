/* global jQuery, wcpayExpressCheckoutParams */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import WCPayAPI from '../checkout/api';
import '../checkout/express-checkout-buttons.scss';
import { getExpressCheckoutData, normalizeLineItems } from './utils/index';
import {
	onConfirmHandler,
	shippingAddressChangeHandler,
	shippingRateChangeHandler,
} from './event-handlers';

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
		 * Abort the payment and display error messages.
		 *
		 * @param {PaymentResponse} payment Payment response instance.
		 * @param {string} message Error message to display.
		 */
		abortPayment: ( payment, message ) => {
			payment.paymentFailed( { reason: 'fail' } );
			wcpayECE.unblock();

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

		unblock: () => {
			$.unblockUI();
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
			const getShippingRates = () => {
				if ( ! options.requestShipping ) {
					return [];
				}

				if ( getExpressCheckoutData( 'is_product_page' ) ) {
					// Despite the name of the property, this seems to be just a single option that's not in an array.
					const {
						shippingOptions: shippingOption,
					} = getExpressCheckoutData( 'product' );

					return [
						{
							id: shippingOption.id,
							amount: shippingOption.amount,
							displayName: shippingOption.label,
						},
					];
				}

				return options.displayItems
					.filter(
						( i ) =>
							i.label === __( 'Shipping', 'woocommerce-payments' )
					)
					.map( ( i ) => ( {
						id: `rate-${ i.label }`,
						amount: i.amount,
						displayName: i.label,
					} ) );
			};

			const shippingRates = getShippingRates();

			// This is a bit of a hack, but we need some way to get the shipping information before rendering the button, and
			// since we don't have any address information at this point it seems best to rely on what came with the cart response.
			// Relying on what's provided in the cart response seems safest since it should always include a valid shipping
			// rate if one is required and available.
			// If no shipping rate is found we can't render the button so we just exit.
			if ( options.requestShipping && ! shippingRates ) {
				return;
			}

			const elements = api.getStripe().elements( {
				mode: options?.mode ?? 'payment',
				amount: options?.total,
				currency: options?.currency,
				paymentMethodCreation: 'manual',
			} );

			const eceButton = wcpayECE.createButton( elements, {
				buttonType: {
					googlePay: getExpressCheckoutData( 'button' ).type,
					applePay: getExpressCheckoutData( 'button' ).type,
				},
			} );

			wcpayECE.showButton( eceButton );

			eceButton.on( 'click', function ( event ) {
				// TODO: handle cases where we need login confirmation.

				if ( getExpressCheckoutData( 'is_product_page' ) ) {
					wcpayECE.addToCart();
				}

				const clickOptions = {
					lineItems: normalizeLineItems( options.displayItems ),
					emailRequired: true,
					shippingAddressRequired: options.requestShipping,
					phoneNumberRequired: options.requestPhone,
					shippingRates,
				};
				wcpayECE.block();
				event.resolve( clickOptions );
			} );

			eceButton.on( 'shippingaddresschange', async ( event ) =>
				shippingAddressChangeHandler( api, event, elements )
			);

			eceButton.on( 'shippingratechange', async ( event ) =>
				shippingRateChangeHandler( api, event, elements )
			);

			eceButton.on( 'confirm', async ( event ) =>
				onConfirmHandler(
					api,
					api.getStripe(),
					elements,
					wcpayECE.completePayment,
					wcpayECE.abortPayment,
					event
				)
			);

			eceButton.on( 'cancel', async () => {
				wcpayECE.unblock();
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
					total: getExpressCheckoutData( 'product' )?.total.amount,
					currency: getExpressCheckoutData( 'product' )?.currency,
					requestShipping:
						getExpressCheckoutData( 'product' )?.needs_shipping ??
						false,
					requestPhone:
						getExpressCheckoutData( 'checkout' )
							?.needs_payer_phone ?? false,
					displayItems:
						wcpayExpressCheckoutParams.product.displayItems,
				} );
			} else {
				// If this is the cart or checkout page, we need to request the
				// cart details.
				api.paymentRequestGetCartDetails().then( ( cart ) => {
					wcpayECE.startExpressCheckoutElement( {
						mode: 'payment',
						total: cart.total.amount,
						currency: getExpressCheckoutData( 'checkout' )
							?.currency_code,
						requestShipping: cart.needs_shipping,
						requestPhone:
							getExpressCheckoutData( 'checkout' )
								?.needs_payer_phone ?? false,
						displayItems: cart.displayItems,
					} );
				} );
			}
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
