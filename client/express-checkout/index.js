/* global jQuery, wcpayExpressCheckoutParams, wcpayECEPayForOrderParams */
import { __ } from '@wordpress/i18n';
import { debounce } from 'lodash';

/**
 * Internal dependencies
 */
import WCPayAPI from '../checkout/api';
import '../checkout/express-checkout-buttons.scss';
import {
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutButtonStyleSettings,
	getExpressCheckoutData,
	normalizeLineItems,
} from './utils/index';
import {
	onAbortPaymentHandler,
	onCancelHandler,
	onClickHandler,
	onCompletePaymentHandler,
	onConfirmHandler,
	onReadyHandler,
	shippingAddressChangeHandler,
	shippingRateChangeHandler,
} from './event-handlers';
import { displayLoginConfirmation } from './utils';

jQuery( ( $ ) => {
	// Don't load if blocks checkout is being loaded.
	if (
		wcpayExpressCheckoutParams.has_block &&
		! wcpayExpressCheckoutParams.is_pay_for_order
	) {
		return;
	}

	const publishableKey = wcpayExpressCheckoutParams.stripe.publishableKey;
	const quantityInputSelector = '.quantity .qty[type=number]';

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

	let wcPayECEError = '';
	const defaultErrorMessage = __(
		'There was an error getting the product information.',
		'woocommerce-payments'
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
			onAbortPaymentHandler( payment, message );

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
			onCompletePaymentHandler( url );
			window.location = url;
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
				qty: $( quantityInputSelector ).val(),
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
				appearance: getExpressCheckoutButtonAppearance(),
			} );

			const eceButton = wcpayECE.createButton(
				elements,
				getExpressCheckoutButtonStyleSettings()
			);

			wcpayECE.showButton( eceButton );

			eceButton.on( 'click', function ( event ) {
				// If login is required for checkout, display redirect confirmation dialog.
				if ( getExpressCheckoutData( 'login_confirmation' ) ) {
					displayLoginConfirmation( event.expressPaymentType );
					return;
				}

				if ( getExpressCheckoutData( 'is_product_page' ) ) {
					const addToCartButton = $( '.single_add_to_cart_button' );

					// First check if product can be added to cart.
					if ( addToCartButton.is( '.disabled' ) ) {
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

					if ( wcPayECEError ) {
						window.alert( wcPayECEError );
						return;
					}

					// Add products to the cart if everything is right.
					wcpayECE.addToCart();
				}

				const clickOptions = {
					lineItems: normalizeLineItems( options.displayItems ),
					emailRequired: true,
					shippingAddressRequired: options.requestShipping,
					phoneNumberRequired: options.requestPhone,
					shippingRates,
				};

				onClickHandler( event );
				event.resolve( clickOptions );
			} );

			eceButton.on( 'shippingaddresschange', async ( event ) =>
				shippingAddressChangeHandler( api, event, elements )
			);

			eceButton.on( 'shippingratechange', async ( event ) =>
				shippingRateChangeHandler( api, event, elements )
			);

			eceButton.on( 'confirm', async ( event ) => {
				const order = options.order ?? 0;

				return onConfirmHandler(
					api,
					api.getStripe(),
					elements,
					wcpayECE.completePayment,
					wcpayECE.abortPayment,
					event,
					order
				);
			} );

			eceButton.on( 'cancel', async () => {
				wcpayECE.paymentAborted = true;
				onCancelHandler();
			} );

			eceButton.on( 'ready', onReadyHandler );

			if ( getExpressCheckoutData( 'is_product_page' ) ) {
				wcpayECE.attachProductPageEventListeners( elements );
			}
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
				qty: $( quantityInputSelector ).val(),
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

		attachProductPageEventListeners: ( elements ) => {
			// WooCommerce Deposits support.
			// Trigger the "woocommerce_variation_has_changed" event when the deposit option is changed.
			// Needs to be defined before the `woocommerce_variation_has_changed` event handler is set.
			$(
				'input[name=wc_deposit_option],input[name=wc_deposit_payment_plan]'
			)
				.off( 'change' )
				.on( 'change', () => {
					$( 'form' )
						.has(
							'input[name=wc_deposit_option],input[name=wc_deposit_payment_plan]'
						)
						.trigger( 'woocommerce_variation_has_changed' );
				} );

			$( document.body )
				.off( 'woocommerce_variation_has_changed' )
				.on( 'woocommerce_variation_has_changed', () => {
					wcpayECE.blockExpressCheckoutButton();

					$.when( wcpayECE.getSelectedProductData() )
						.then( ( response ) => {
							const isDeposits = wcpayECE.productHasDepositOption();
							/**
							 * If the customer aborted the express checkout,
							 * we need to re init the express checkout button to ensure the shipping
							 * options are refetched. If the customer didn't abort the express checkout,
							 * and the product's shipping status is consistent,
							 * we can simply update the express checkout button with the new total and display items.
							 */
							const needsShipping =
								! wcpayECE.paymentAborted &&
								getExpressCheckoutData( 'product' )
									.needs_shipping === response.needs_shipping;

							if ( ! isDeposits && needsShipping ) {
								elements.update( {
									amount: response.total.amount,
								} );
							} else {
								wcpayECE.reInitExpressCheckoutElement(
									response
								);
							}
						} )
						.catch( () => {
							wcpayECE.hide();
						} )
						.always( () => {
							wcpayECE.unblockExpressCheckoutButton();
						} );
				} );

			$( '.quantity' )
				.off( 'input', '.qty' )
				.on(
					'input',
					'.qty',
					debounce( () => {
						wcpayECE.blockExpressCheckoutButton();
						wcPayECEError = '';

						$.when( wcpayECE.getSelectedProductData() )
							.then(
								( response ) => {
									// In case the server returns an unexpected response
									if ( typeof response !== 'object' ) {
										wcPayECEError = defaultErrorMessage;
									}

									if (
										! wcpayECE.paymentAborted &&
										getExpressCheckoutData( 'product' )
											.needs_shipping ===
											response.needs_shipping
									) {
										elements.update( {
											amount: response.total.amount,
										} );
									} else {
										wcpayECE.reInitExpressCheckoutElement(
											response
										);
									}
								},
								( response ) => {
									wcPayECEError =
										response.responseJSON?.error ??
										defaultErrorMessage;
								}
							)
							.always( function () {
								wcpayECE.unblockExpressCheckoutButton();
							} );
					}, 250 )
				);
		},

		reInitExpressCheckoutElement: ( response ) => {
			wcpayExpressCheckoutParams.product.needs_shipping =
				response.needs_shipping;
			wcpayExpressCheckoutParams.product.total = response.total;
			wcpayExpressCheckoutParams.product.displayItems =
				response.displayItems;
			wcpayECE.init();
		},

		blockExpressCheckoutButton: () => {
			// check if element isn't already blocked before calling block() to avoid blinking overlay issues
			// blockUI.isBlocked is either undefined or 0 when element is not blocked
			if (
				$( '#wcpay-express-checkout-element' ).data(
					'blockUI.isBlocked'
				)
			) {
				return;
			}

			$( '#wcpay-express-checkout-element' ).block( { message: null } );
		},

		unblockExpressCheckoutButton: () => {
			wcpayECE.show();
			$( '#wcpay-express-checkout-element' ).unblock();
		},

		getElements: () => {
			return $(
				'.wcpay-payment-request-wrapper,#wcpay-express-checkout-button-separator'
			);
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

		productHasDepositOption() {
			return !! $( 'form' ).has(
				'input[name=wc_deposit_option],input[name=wc_deposit_payment_plan]'
			).length;
		},

		/**
		 * Initialize event handlers and UI state
		 */
		init: () => {
			if ( wcpayExpressCheckoutParams.is_pay_for_order ) {
				if ( ! window.wcpayECEPayForOrderParams ) {
					return;
				}

				const {
					total: { amount: total },
					displayItems,
					order,
				} = wcpayECEPayForOrderParams;

				wcpayECE.startExpressCheckoutElement( {
					mode: 'payment',
					total,
					currency: getExpressCheckoutData( 'checkout' )
						?.currency_code,
					requestShipping: false,
					requestPhone:
						getExpressCheckoutData( 'checkout' )
							?.needs_payer_phone ?? false,
					displayItems,
					order,
				} );
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

			// After initializing a new express checkout button, we need to reset the paymentAborted flag.
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
