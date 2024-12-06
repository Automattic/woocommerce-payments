/* global jQuery, wcpayExpressCheckoutParams */
import { __ } from '@wordpress/i18n';
import { addAction, removeAction } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import WCPayAPI from '../checkout/api';
import '../checkout/express-checkout-buttons.scss';
import './compatibility/wc-deposits';
import './compatibility/wc-order-attribution';
import './compatibility/wc-product-page';
import {
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutButtonStyleSettings,
	getExpressCheckoutData,
	displayLoginConfirmation,
} from './utils';
import {
	onAbortPaymentHandler,
	onCancelHandler,
	onClickHandler,
	onCompletePaymentHandler,
	onConfirmHandler,
	onReadyHandler,
	shippingAddressChangeHandler,
	shippingRateChangeHandler,
	setCartApiHandler,
	getCartApiHandler,
} from './event-handlers';
import ExpressCheckoutOrderApi from './order-api';
import { getUPEConfig } from 'wcpay/utils/checkout';
import expressCheckoutButtonUi from './button-ui';
import {
	transformCartDataForDisplayItems,
	transformCartDataForShippingRates,
	transformPrice,
} from './transformers/wc-to-stripe';

/**
 * External dependencies
 */
import { addFilter, removeFilter } from '@wordpress/hooks';

jQuery( ( $ ) => {
	// Don't load if blocks checkout is being loaded.
	if (
		getExpressCheckoutData( 'has_block' ) &&
		getExpressCheckoutData( 'button_context' ) !== 'pay_for_order'
	) {
		return;
	}

	const publishableKey = getExpressCheckoutData( 'stripe' ).publishableKey;
	const quantityInputSelector = '.quantity .qty[type=number]';

	if ( ! publishableKey ) {
		// If no configuration is present, probably this is not the checkout page.
		return;
	}

	const api = new WCPayAPI(
		{
			publishableKey,
			accountId: getExpressCheckoutData( 'stripe' ).accountId,
			locale: getExpressCheckoutData( 'stripe' ).locale,
		},
		// A promise-based interface to jQuery.post.
		( url, args ) => {
			return new Promise( ( resolve, reject ) => {
				jQuery.post( url, args ).then( resolve ).fail( reject );
			} );
		}
	);

	if ( getExpressCheckoutData( 'button_context' ) === 'pay_for_order' ) {
		setCartApiHandler(
			new ExpressCheckoutOrderApi( {
				orderId: getUPEConfig( 'order_id' ),
				key: getUPEConfig( 'key' ),
				billingEmail: getUPEConfig( 'billing_email' ),
			} )
		);
	}

	expressCheckoutButtonUi.init( {
		elementId: '#wcpay-express-checkout-element',
		$separator: jQuery( '#wcpay-express-checkout-button-separator' ),
	} );

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
			onAbortPaymentHandler();

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
			onCompletePaymentHandler();
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

			// TODO ~FR: replace with cartApi
			return api.expressCheckoutECEAddToCart( data );
		},

		/**
		 * Starts the Express Checkout Element
		 *
		 * @param {Object} options ECE options.
		 */
		startExpressCheckoutElement: async ( options ) => {
			const stripe = await api.getStripe();
			const elements = stripe.elements( {
				mode: options.mode ?? 'payment',
				amount: options.total,
				currency: options.currency,
				paymentMethodCreation: 'manual',
				appearance: getExpressCheckoutButtonAppearance(),
				locale: getExpressCheckoutData( 'stripe' )?.locale ?? 'en',
			} );

			const eceButton = elements.create(
				'expressCheckout',
				getExpressCheckoutButtonStyleSettings()
			);

			expressCheckoutButtonUi.renderButton( eceButton );

			eceButton.on( 'loaderror', () => {
				wcPayECEError = __(
					'The cart is incompatible with express checkout.',
					'woocommerce-payments'
				);
				if ( ! document.getElementById( 'wcpay-woopay-button' ) ) {
					expressCheckoutButtonUi.getButtonSeparator().hide();
				}
			} );

			eceButton.on( 'click', function ( event ) {
				// If login is required for checkout, display redirect confirmation dialog.
				if ( getExpressCheckoutData( 'login_confirmation' ) ) {
					displayLoginConfirmation( event.expressPaymentType );
					return;
				}

				if (
					getExpressCheckoutData( 'button_context' ) === 'product'
				) {
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
					// TODO ~FR: use cartApi
					wcpayECE.addToCart();
				}

				const clickOptions = {
					lineItems: options.displayItems,
					emailRequired: true,
					shippingAddressRequired: options.requestShipping,
					phoneNumberRequired: options.requestPhone,
					shippingRates: options.shippingRates,
					allowedShippingCountries: getExpressCheckoutData(
						'checkout'
					).allowed_shipping_countries,
				};

				onClickHandler( event );
				event.resolve( clickOptions );
			} );

			eceButton.on( 'shippingaddresschange', async ( event ) =>
				shippingAddressChangeHandler( event, elements )
			);

			eceButton.on( 'shippingratechange', async ( event ) =>
				shippingRateChangeHandler( event, elements )
			);

			eceButton.on( 'confirm', async ( event ) => {
				return onConfirmHandler(
					api,
					stripe,
					elements,
					wcpayECE.completePayment,
					wcpayECE.abortPayment,
					event
				);
			} );

			eceButton.on( 'cancel', async () => {
				wcpayECE.paymentAborted = true;
				onCancelHandler();
			} );

			eceButton.on( 'ready', ( onReadyParams ) => {
				onReadyHandler( onReadyParams );

				if (
					onReadyParams?.availablePaymentMethods &&
					Object.values(
						onReadyParams.availablePaymentMethods
					).filter( Boolean ).length
				) {
					expressCheckoutButtonUi.showContainer();
					expressCheckoutButtonUi.getButtonSeparator().show();
				}
			} );

			removeAction(
				'wcpay.express-checkout.update-button-data',
				'automattic/wcpay/express-checkout'
			);
			addAction(
				'wcpay.express-checkout.update-button-data',
				'automattic/wcpay/express-checkout',
				async () => {
					try {
						expressCheckoutButtonUi.blockButton();

						const newCartData = await _self.getCartData();
						// checking if items needed shipping, before assigning new cart data.
						const didItemsNeedShipping =
							_self.initialProductData?.needs_shipping ||
							_self.cachedCartData?.needs_shipping;

						_self.cachedCartData = newCartData;

						/**
						 * If the customer aborted the payment request, we need to re init the payment request button to ensure the shipping
						 * options are re-fetched. If the customer didn't abort the payment request, and the product's shipping status is
						 * consistent, we can simply update the payment request button with the new total and display items.
						 */
						if (
							! wcpayECE.paymentAborted &&
							didItemsNeedShipping === newCartData.needs_shipping
						) {
							elements.update( {
								total: {
									label: getExpressCheckoutData(
										'total_label'
									),
									amount: transformPrice(
										parseInt(
											newCartData.totals.total_price,
											10
										) -
											parseInt(
												newCartData.totals
													.total_refund || 0,
												10
											),
										newCartData.totals
									),
								},
								displayItems: transformCartDataForDisplayItems(
									newCartData
								),
							} );
						} else {
							await _self.init();
						}

						expressCheckoutButtonUi.unblockButton();
					} catch ( e ) {
						expressCheckoutButtonUi.hide();
					}
				}
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

		/**
		 * Initialize event handlers and UI state
		 */
		init: async () => {
			if ( getExpressCheckoutData( 'button_context' ) === 'product' ) {
				// on product pages, we need to interact with an anonymous cart to checkout the product,
				// so that we don't affect the products in the main cart.
				// On cart, checkout, place order pages we instead use the cart itself.
				getCartApiHandler().useSeparateCart();

				await wcpayECE.startExpressCheckoutElement( {
					mode: 'payment',
					total: getExpressCheckoutData( 'product' )?.total.amount,
					currency: getExpressCheckoutData( 'product' )?.currency,
					requestShipping:
						getExpressCheckoutData( 'product' )?.needs_shipping ??
						false,
					requestPhone:
						getExpressCheckoutData( 'checkout' )
							?.needs_payer_phone ?? false,
					displayItems: getExpressCheckoutData( 'product' )
						.displayItems,
				} );
			} else {
				// If this is the cart page, or checkout page, or pay-for-order page, we need to request the cart details.
				const cartData = await getCartApiHandler().getCart();
				const total = transformPrice(
					parseInt( cartData.totals.total_price, 10 ) -
						parseInt( cartData.totals.total_refund || 0, 10 ),
					cartData.totals
				);
				if ( total === 0 ) {
					expressCheckoutButtonUi.hideContainer();
					expressCheckoutButtonUi.getButtonSeparator().hide();
				} else {
					await wcpayECE.startExpressCheckoutElement( {
						mode: 'payment',
						total,
						currency: cartData.totals.currency_code.toLowerCase(),
						// pay-for-order should never display the shipping selection.
						requestShipping:
							getExpressCheckoutData( 'button_context' ) !==
								'pay_for_order' && cartData.needs_shipping,
						shippingRates: transformCartDataForShippingRates(
							cartData
						),
						requestPhone:
							getExpressCheckoutData( 'checkout' )
								?.needs_payer_phone ?? false,
						displayItems: transformCartDataForDisplayItems(
							cartData
						),
					} );
				}
			}

			// After initializing a new express checkout button, we need to reset the paymentAborted flag.
			wcpayECE.paymentAborted = false;
		},
	};

	// We don't need to initialize ECE on the checkout page now because it will be initialized by updated_checkout event.
	if (
		getExpressCheckoutData( 'button_context' ) !== 'checkout' ||
		getExpressCheckoutData( 'button_context' ) === 'pay_for_order'
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
