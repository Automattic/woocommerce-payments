/* global jQuery, wcpayPaymentRequestParams, wcpayPaymentRequestPayForOrderParams */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { doAction, addAction } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import WCPayAPI from '../checkout/api';

import {
	setPaymentRequestBranding,
	trackPaymentRequestButtonClick,
	trackPaymentRequestButtonLoad,
} from './tracking';
import {
	transformCartDataForDisplayItems,
	transformCartDataForShippingOptions,
	transformStripePaymentMethodForStoreApi,
	transformStripeShippingAddressForStoreApi,
} from './transformers';
import paymentRequestButtonUi from './button-ui';
import './wc-product-variations-compatibility';
import './wc-deposits-compatibility';
import '../checkout/express-checkout-buttons.scss';

import {
	getPaymentRequest,
	displayLoginConfirmationDialog,
	getPaymentRequestData,
} from './frontend-utils';
import PaymentRequestCartApi from './cart-api';
import debounce from './debounce';

jQuery( ( $ ) => {
	// Don't load if blocks checkout is being loaded.
	if (
		wcpayPaymentRequestParams.has_block &&
		wcpayPaymentRequestParams.button_context !== 'pay_for_order'
	) {
		return;
	}

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
	const paymentRequestCartApi = new PaymentRequestCartApi();

	/**
	 * Object to handle Stripe payment forms.
	 */
	const wcpayPaymentRequest = {
		/**
		 * Whether the payment was aborted by the customer.
		 */
		paymentAborted: false,

		/**
		 * The cart data represented if the product were to be added to the cart (or, on cart/checkout pages, the cart data itself).
		 * This is useful on product pages to understand if shipping is needed.
		 */
		cachedCartData: undefined,

		/**
		 * Starts the payment request
		 */
		startPaymentRequest: async () => {
			// TODO ~FR: is this creating multiple handlers to events on different `paymentRequest` objects?
			const paymentRequest = getPaymentRequest( {
				stripe: api.getStripe(),
				cartData: wcpayPaymentRequest.cachedCartData,
			} );

			// Check the availability of the Payment Request API first.
			const paymentPermissionResult = await paymentRequest.canMakePayment();
			if ( ! paymentPermissionResult ) {
				doAction( 'wcpay.payment-request.availability', {
					paymentRequestType: null,
				} );
				return;
			}

			// TODO: Don't display custom button when paymentRequestType is `apple_pay` or `google_pay`.
			let buttonBranding = null;
			if ( paymentPermissionResult.applePay ) {
				buttonBranding = 'apple_pay';
			} else if ( paymentPermissionResult.googlePay ) {
				buttonBranding = 'google_pay';
			} else {
				buttonBranding = 'payment_request_api';
			}

			doAction( 'wcpay.payment-request.availability', {
				paymentRequestType: buttonBranding,
			} );

			setPaymentRequestBranding( buttonBranding );
			trackPaymentRequestButtonLoad(
				wcpayPaymentRequestParams.button_context
			);

			// On PDP pages, we need to use an anonymous cart to checkout.
			// On cart, checkout, place order pages we instead use the cart itself.
			if ( wcpayPaymentRequestParams.button_context === 'product' ) {
				paymentRequestCartApi.createAnonymousCart();
			}

			const prButton = api
				.getStripe()
				.elements()
				.create( 'paymentRequestButton', {
					paymentRequest: paymentRequest,
					style: {
						paymentRequestButton: {
							type: wcpayPaymentRequestParams.button.type,
							theme: wcpayPaymentRequestParams.button.theme,
							height:
								wcpayPaymentRequestParams.button.height + 'px',
						},
					},
				} );
			paymentRequestButtonUi.showButton( prButton );

			wcpayPaymentRequest.attachPaymentRequestButtonEventListeners(
				paymentRequest
			);
			addAction(
				'wcpay.payment-request.update-button-data',
				'automattic/wcpay/payment-request',
				async () => {
					const newCartData = await wcpayPaymentRequest.getCartData();

					/**
					 * If the customer aborted the payment request, we need to re init the payment request button to ensure the shipping
					 * options are re-fetched. If the customer didn't abort the payment request, and the product's shipping status is
					 * consistent, we can simply update the payment request button with the new total and display items.
					 */
					if (
						! wcpayPaymentRequest.paymentAborted &&
						wcpayPaymentRequest.cachedCartData.needs_shipping ===
							newCartData.needs_shipping
					) {
						wcpayPaymentRequest.cachedCartData = newCartData;
						paymentRequest.update( {
							total: {
								label: getPaymentRequestData( 'total_label' ),
								amount: parseInt(
									newCartData.totals.total_price,
									10
								),
							},
							requestShipping: newCartData.needs_shipping,
							// TODO ~FR: get transform utility
							displayItems: transformCartDataForDisplayItems(
								newCartData
							),
						} );
					} else {
						wcpayPaymentRequest.cachedCartData = newCartData;
						wcpayPaymentRequest.init();
					}
				}
			);

			const $addToCartButton = $( '.single_add_to_cart_button' );

			prButton.on( 'click', ( event ) => {
				trackPaymentRequestButtonClick( 'product' );

				// If login is required for checkout, display redirect confirmation dialog.
				if ( wcpayPaymentRequestParams.login_confirmation ) {
					event.preventDefault();
					displayLoginConfirmationDialog( buttonBranding );
					return;
				}

				// First check if product can be added to cart.
				if ( $addToCartButton.is( '.disabled' ) ) {
					event.preventDefault(); // Prevent showing payment request modal.
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

				paymentRequestCartApi.addProductToCart();
			} );

			paymentRequest.on( 'cancel', () => {
				wcpayPaymentRequest.paymentAborted = true;
			} );

			paymentRequest.on( 'shippingaddresschange', async ( event ) => {
				try {
					const cartData = await paymentRequestCartApi.updateCustomer(
						transformStripeShippingAddressForStoreApi(
							event.shippingAddress
						)
					);

					event.updateWith( {
						// Possible statuses: https://docs.stripe.com/js/appendix/payment_response#payment_response_object-complete
						status: 'success',
						shippingOptions: transformCartDataForShippingOptions(
							cartData
						),
						total: {
							label: getPaymentRequestData( 'total_label' ),
							amount: parseInt( cartData.totals.total_price, 10 ),
						},
						displayItems: transformCartDataForDisplayItems(
							cartData
						),
					} );

					wcpayPaymentRequest.cachedCartData = cartData;
				} catch ( error ) {
					// Possible statuses: https://docs.stripe.com/js/appendix/payment_response#payment_response_object-complete
					event.updateWith( {
						status: 'fail',
					} );
				}
			} );

			paymentRequest.on( 'shippingoptionchange', async ( event ) => {
				try {
					const cartData = await paymentRequestCartApi.selectShippingRate(
						{ package_id: 0, rate_id: event.shippingOption.id }
					);

					event.updateWith( {
						status: 'success',
						total: {
							label: getPaymentRequestData( 'total_label' ),
							amount: parseInt( cartData.totals.total_price, 10 ),
						},
						displayItems: transformCartDataForDisplayItems(
							cartData
						),
					} );
					wcpayPaymentRequest.cachedCartData = cartData;
				} catch ( error ) {
					event.updateWith( { status: 'fail' } );
				}
			} );

			paymentRequest.on( 'paymentmethod', async ( event ) => {
				// TODO ~FR: this works for PDPs - need to handle checkout scenarios for pay-for-order, cart, checkout.
				try {
					const response = await paymentRequestCartApi.placeOrder(
						transformStripePaymentMethodForStoreApi( event )
					);

					const confirmationRequest = api.confirmIntent(
						response.redirect
					);
					// We need to call `complete` before redirecting to close the dialog for 3DS.
					event.complete( 'success' );

					let redirectUrl = '';

					// `true` means there is no intent to confirm.
					if ( confirmationRequest === true ) {
						redirectUrl = response.redirect;
					} else {
						redirectUrl = await confirmationRequest;
					}

					$.blockUI( {
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6,
						},
					} );

					window.location = redirectUrl;
				} catch ( error ) {
					event.complete( 'fail' );

					$( '.woocommerce-error' ).remove();

					const $container = $(
						'.woocommerce-notices-wrapper'
					).first();

					if ( $container.length ) {
						$container.append(
							$( '<div class="woocommerce-error" />' ).text(
								error.message
							)
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
				}
			} );
		},

		attachPaymentRequestButtonEventListeners: () => {
			// Block the payment request button as soon as an "input" event is fired, to avoid sync issues
			// when the customer clicks on the button before the debounced event is processed.
			const $quantityInput = $( '.quantity' );
			const handleQuantityChange = () => {
				paymentRequestButtonUi.blockButton();
			};
			$quantityInput.on( 'input', '.qty', handleQuantityChange );
			$quantityInput.on(
				'input',
				'.qty',
				debounce( 250, () => {
					doAction( 'wcpay.payment-request.update-button-data' );
					paymentRequestButtonUi.unblockButton();
				} )
			);
		},

		getCartData: async () => {
			const temporaryCart = new PaymentRequestCartApi();
			await temporaryCart.createAnonymousCart();

			const cartData = await temporaryCart.addProductToCart();

			// no need to wait for the request to end, it can be done asynchronously.
			temporaryCart.emptyCart();

			return cartData;
		},

		/**
		 * Initialize event handlers and UI state
		 */
		init: async ( { refresh = false } = {} ) => {
			if ( ! wcpayPaymentRequest.cachedCartData || refresh ) {
				wcpayPaymentRequest.cachedCartData = await wcpayPaymentRequest.getCartData();
			}

			wcpayPaymentRequest.startPaymentRequest();

			// After initializing a new payment request, we need to reset the paymentAborted flag.
			wcpayPaymentRequest.paymentAborted = false;
		},
	};

	// We don't need to initialize payment request on the checkout page now because it will be initialized by updated_checkout event.
	if (
		wcpayPaymentRequestParams.button_context !== 'checkout' ||
		wcpayPaymentRequestParams.button_context === 'pay_for_order'
	) {
		wcpayPaymentRequest.init();
	}

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_cart_totals', () => {
		wcpayPaymentRequest.init( { refresh: true } );
	} );

	// We need to refresh payment request data when total is updated.
	$( document.body ).on( 'updated_checkout', () => {
		wcpayPaymentRequest.init( { refresh: true } );
	} );
} );
