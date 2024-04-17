/* global jQuery, wcpayPaymentRequestParams, wcpayPaymentRequestPayForOrderParams */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { doAction } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import WCPayAPI from '../checkout/api';

import {
	setPaymentRequestType,
	trackPaymentRequestButtonClick,
	trackPaymentRequestButtonLoad,
} from './tracking';
import paymentRequestButtonUi from './button-ui';
import './wc-product-variations-compatibility';
import './wc-bookings-compatibility';
import '../checkout/express-checkout-buttons.scss';

import {
	getPaymentRequest,
	displayLoginConfirmationDialog,
} from './frontend-utils';
import paymentRequestCartInterface from 'wcpay/tokenized-payment-request/cart-interface';

const doActionPaymentRequestAvailability = ( args ) => {
	doAction( 'wcpay.payment-request.availability', args );
};

// TODO ~FR
const paymentMethodHandler = () => null;

jQuery( ( $ ) => {
	const publishableKey = wcpayPaymentRequestParams.stripe.publishableKey;

	if ( ! publishableKey ) {
		// If no configuration is present, we can't do anything.
		return;
	}

	// initializing the UI's container.
	paymentRequestButtonUi.init( {
		$container: jQuery( '#wcpay-payment-request-button' ),
	} );

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
		/**
		 * Whether the payment was aborted by the customer.
		 */
		paymentAborted: false,

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
			$.blockUI( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6,
				},
			} );

			window.location = url;
		},

		/**
		 * Adds the item to the cart and return cart details.
		 *
		 * @return {Promise} Promise for the request to the server.
		 */
		addToCart: () => {},

		/**
		 * Starts the payment request
		 *
		 * @param {Object} options Payment request options.
		 */
		startPaymentRequest: ( {
			stripe,
			total,
			requestShipping,
			displayItems,
			handler = paymentMethodHandler,
		} ) => {
			const paymentRequest = getPaymentRequest( {
				stripe,
				total,
				requestShipping,
				displayItems,
			} );
			const elements = api.getStripe().elements();
			const prButton = elements.create( 'paymentRequestButton', {
				paymentRequest: paymentRequest,
				style: {
					paymentRequestButton: {
						type: wcpayPaymentRequestParams.button.type,
						theme: wcpayPaymentRequestParams.button.theme,
						height: wcpayPaymentRequestParams.button.height + 'px',
					},
				},
			} );

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
				let type = null;
				if ( result.applePay ) {
					type = 'apple_pay';
				} else if ( result.googlePay ) {
					type = 'google_pay';
				} else {
					type = 'payment_request_api';
				}

				setPaymentRequestType( type );
				doActionPaymentRequestAvailability( {
					paymentRequestType: type,
				} );

				trackPaymentRequestButtonLoad(
					wcpayPaymentRequestParams.button_context
				);

				wcpayPaymentRequest.attachPaymentRequestButtonEventListeners(
					prButton,
					paymentRequest
				);

				paymentRequestButtonUi.showButton( prButton );
				paymentRequestCartInterface.createAnonymousCart();
			} );

			paymentRequest.on( 'cancel', () => {
				wcpayPaymentRequest.paymentAborted = true;
			} );

			paymentRequest.on( 'shippingaddresschange', ( event ) =>
				console.log( '### shippingaddresschange', event )
			);

			paymentRequest.on( 'shippingoptionchange', ( event ) =>
				console.log( '### shippingoptionchange', event )
			);

			paymentRequest.on( 'paymentmethod', ( event ) => {
				console.log( '### paymentmethod', event );
			} );
		},

		getSelectedProductData: () => {
			// TODO ~FR
			return Promise.reject();
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

		attachPaymentRequestButtonEventListeners: (
			prButton,
			paymentRequest
		) => {
			const $addToCartButton = $( '.single_add_to_cart_button' );

			prButton.on( 'click', ( evt ) => {
				trackPaymentRequestButtonClick( 'product' );

				// If login is required for checkout, display redirect confirmation dialog.
				if ( wcpayPaymentRequestParams.login_confirmation ) {
					evt.preventDefault();
					// TODO ~FR
					const paymentRequestType = '';
					displayLoginConfirmationDialog( paymentRequestType );
					return;
				}

				// First check if product can be added to cart.
				if ( $addToCartButton.is( '.disabled' ) ) {
					evt.preventDefault(); // Prevent showing payment request modal.
					if (
						$addToCartButton.is( '.wc-variation-is-unavailable' )
					) {
						window.alert(
							window.wc_add_to_cart_variation_params
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

				paymentRequestCartInterface.addProductToCart();
				// TODO ~FR
				evt.preventDefault();
			} );

			// Block the payment request button as soon as an "input" event is fired, to avoid sync issues
			// when the customer clicks on the button before the debounced event is processed.
			const $quantityInput = $( '.quantity' );
			$quantityInput.on( 'input', '.qty', () => {
				paymentRequestButtonUi.blockButton();
			} );

			$quantityInput.off( 'input', '.qty' ).on(
				'input',
				'.qty',
				wcpayPaymentRequest.debounce( 250, () => {
					paymentRequestButtonUi.blockButton();

					$.when( wcpayPaymentRequest.getSelectedProductData() ).then(
						( response ) => {
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
							paymentRequestButtonUi.unblockButton();
						}
					);
				} )
			);
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
			wcpayPaymentRequest.startPaymentRequest( {
				stripe: api.getStripe(),
				total: wcpayPaymentRequestParams.product.total.amount,
				requestShipping:
					wcpayPaymentRequestParams.product.needs_shipping,
				displayItems: wcpayPaymentRequestParams.product.displayItems,
			} );

			// After initializing a new payment request, we need to reset the paymentAborted flag.
			wcpayPaymentRequest.paymentAborted = false;
		},
	};

	wcpayPaymentRequest.init();

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_cart_totals', () => {
		// TODO ~FR
		wcpayPaymentRequest.init();
	} );

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_checkout', () => {
		// TODO ~FR
		wcpayPaymentRequest.init();
	} );

	$( document.body ).on( 'woocommerce_variation_has_changed', () => {
		// TODO ~FR
		return;
		paymentRequestButtonUi.blockButton();

		wcpayPaymentRequest
			.getSelectedProductData()
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
					wcpayPaymentRequest.reInitPaymentRequest( response );
				}

				paymentRequestButtonUi.unblockButton();
			} )
			.catch( () => {
				paymentRequestButtonUi.hide();
			} );
	} );

	// WooCommerce Deposits support.
	// Trigger the "woocommerce_variation_has_changed" event when the deposit option is changed.
	$( 'input[name=wc_deposit_option],input[name=wc_deposit_payment_plan]' ).on(
		'change',
		() => {
			$( 'form' )
				.has(
					'input[name=wc_deposit_option],input[name=wc_deposit_payment_plan]'
				)
				.trigger( 'woocommerce_variation_has_changed' );
		}
	);
} );
