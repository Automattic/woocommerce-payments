/* global jQuery */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	doAction,
	applyFilters,
	removeFilter,
	addFilter,
} from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import {
	setPaymentRequestBranding,
	trackPaymentRequestButtonClick,
	trackPaymentRequestButtonLoad,
} from './tracking';
import {
	transformStripePaymentMethodForStoreApi,
	transformStripeShippingAddressForStoreApi,
} from './transformers/stripe-to-wc';
import {
	transformCartDataForDisplayItems,
	transformCartDataForShippingOptions,
	transformPrice,
} from './transformers/wc-to-stripe';
import paymentRequestButtonUi from './button-ui';
import {
	getPaymentRequest,
	displayLoginConfirmationDialog,
	getPaymentRequestData,
} from './frontend-utils';
import PaymentRequestCartApi from './cart-api';
import debounce from './debounce';

const noop = () => null;

/**
 * Class to handle Stripe payment forms.
 */
export default class WooPaymentsPaymentRequest {
	/**
	 * Whether the payment was aborted by the customer.
	 */
	isPaymentAborted = false;

	/**
	 * Whether global listeners have been added.
	 */
	areListenersInitialized = false;

	/**
	 * The cart data represented if the product were to be added to the cart (or, on cart/checkout pages, the cart data itself).
	 * This is useful on product pages to understand if shipping is needed.
	 */
	cachedCartData = undefined;

	/**
	 * API to interface with the cart.
	 *
	 * @type {PaymentRequestCartApi}
	 */
	paymentRequestCartApi = undefined;

	/**
	 * WCPayAPI instance.
	 *
	 * @type {WCPayAPI}
	 */
	wcpayApi = undefined;

	/**
	 * On page load for product pages, we might get some data from the backend (which might get overwritten later).
	 */
	initialProductData = undefined;

	constructor( { wcpayApi, paymentRequestCartApi, productData } ) {
		this.wcpayApi = wcpayApi;
		this.paymentRequestCartApi = paymentRequestCartApi;
		this.initialProductData = productData;
	}

	/**
	 * Starts the payment request
	 */
	async startPaymentRequest() {
		// reference to this class' instance, to be used inside callbacks to avoid `this` misunderstandings.
		const _self = this;
		// TODO: is this creating multiple handlers to events on different `paymentRequest` objects?
		const paymentRequest = getPaymentRequest( {
			stripe: this.wcpayApi.getStripe(),
			cartData: this.cachedCartData,
			productData: this.initialProductData,
		} );

		// Check the availability of the Payment Request API first.
		const paymentPermissionResult = await paymentRequest.canMakePayment();
		if ( ! paymentPermissionResult ) {
			doAction( 'wcpay.payment-request.availability', {
				paymentRequestType: null,
			} );
			return;
		}

		const buttonBranding = paymentPermissionResult.applePay
			? 'apple_pay'
			: 'google_pay';

		doAction( 'wcpay.payment-request.availability', {
			paymentRequestType: buttonBranding,
		} );

		setPaymentRequestBranding( buttonBranding );
		trackPaymentRequestButtonLoad(
			getPaymentRequestData( 'button_context' )
		);

		// On PDP pages, we need to use an anonymous cart to check out.
		// On cart, checkout, place order pages we instead use the cart itself.
		if ( getPaymentRequestData( 'button_context' ) === 'product' ) {
			await this.paymentRequestCartApi.createAnonymousCart();
		}

		const paymentRequestButton = this.wcpayApi
			.getStripe()
			.elements()
			.create( 'paymentRequestButton', {
				paymentRequest: paymentRequest,
				style: {
					paymentRequestButton: {
						type: getPaymentRequestData( 'button' ).type,
						theme: getPaymentRequestData( 'button' ).theme,
						height: getPaymentRequestData( 'button' ).height + 'px',
					},
				},
			} );
		paymentRequestButtonUi.showButton( paymentRequestButton );

		if ( getPaymentRequestData( 'button_context' ) === 'pay_for_order' ) {
			paymentRequestButton.on( 'click', () => {
				trackPaymentRequestButtonClick( 'pay_for_order' );
			} );
		}

		if ( getPaymentRequestData( 'button_context' ) === 'product' ) {
			this.attachPaymentRequestButtonEventListeners();
		}

		removeFilter(
			'wcpay.payment-request.update-button-data',
			'automattic/wcpay/payment-request'
		);
		addFilter(
			'wcpay.payment-request.update-button-data',
			'automattic/wcpay/payment-request',
			async ( previousPromise ) => {
				// Wait for previous filters
				await previousPromise;

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
					! _self.isPaymentAborted &&
					didItemsNeedShipping === newCartData.needs_shipping
				) {
					paymentRequest.update( {
						total: {
							label: getPaymentRequestData( 'total_label' ),
							amount: transformPrice(
								parseInt( newCartData.totals.total_price, 10 ) -
									parseInt(
										newCartData.totals.total_refund || 0,
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
			}
		);

		if ( getPaymentRequestData( 'button_context' ) === 'product' ) {
			const $addToCartButton = jQuery( '.single_add_to_cart_button' );

			paymentRequestButton.on( 'click', ( event ) => {
				trackPaymentRequestButtonClick( 'product' );

				// If login is required for checkout, display redirect confirmation dialog.
				if ( getPaymentRequestData( 'login_confirmation' ) ) {
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
							window?.wc_add_to_cart_variation_params
								?.i18n_make_a_selection_text ||
								__(
									'Please select some product options before adding this product to your cart.',
									'woocommerce-payments'
								)
						);
					}
					return;
				}

				_self.paymentRequestCartApi.addProductToCart();
			} );
		}

		paymentRequest.on( 'cancel', () => {
			_self.isPaymentAborted = true;

			if ( getPaymentRequestData( 'button_context' ) === 'product' ) {
				// clearing the cart to avoid issues with products with low or limited availability
				// being held hostage by customers cancelling the PRB.
				_self.paymentRequestCartApi.emptyCart();
			}
		} );

		paymentRequest.on( 'shippingaddresschange', async ( event ) => {
			try {
				// Please note that the `event.shippingAddress` might not contain all the fields.
				// Some fields might not be present (like `line_1` or `line_2`) due to semi-anonymized data.
				const cartData = await _self.paymentRequestCartApi.updateCustomer(
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
						amount: transformPrice(
							parseInt( cartData.totals.total_price, 10 ) -
								parseInt(
									cartData.totals.total_refund || 0,
									10
								),
							cartData.totals
						),
					},
					displayItems: transformCartDataForDisplayItems( cartData ),
				} );

				_self.cachedCartData = cartData;
			} catch ( error ) {
				// Possible statuses: https://docs.stripe.com/js/appendix/payment_response#payment_response_object-complete
				event.updateWith( {
					status: 'fail',
				} );
			}
		} );

		paymentRequest.on( 'shippingoptionchange', async ( event ) => {
			try {
				const cartData = await _self.paymentRequestCartApi.selectShippingRate(
					{ package_id: 0, rate_id: event.shippingOption.id }
				);

				event.updateWith( {
					status: 'success',
					total: {
						label: getPaymentRequestData( 'total_label' ),
						amount: transformPrice(
							parseInt( cartData.totals.total_price, 10 ) -
								parseInt(
									cartData.totals.total_refund || 0,
									10
								),
							cartData.totals
						),
					},
					displayItems: transformCartDataForDisplayItems( cartData ),
				} );
				_self.cachedCartData = cartData;
			} catch ( error ) {
				event.updateWith( { status: 'fail' } );
			}
		} );

		paymentRequest.on( 'paymentmethod', async ( event ) => {
			// TODO: this works for PDPs - need to handle checkout scenarios for cart, checkout.
			try {
				const response = await _self.paymentRequestCartApi.placeOrder( {
					// adding extension data as a separate action,
					// so that we make it harder for external plugins to modify or intercept checkout data.
					...transformStripePaymentMethodForStoreApi( event ),
					extensions: applyFilters(
						'wcpay.payment-request.cart-place-order-extension-data',
						{}
					),
				} );

				const confirmationRequest = _self.wcpayApi.confirmIntent(
					response.payment_result.redirect_url
				);
				// We need to call `complete` before redirecting to close the dialog for 3DS.
				event.complete( 'success' );

				let redirectUrl = '';

				// `true` means there is no intent to confirm.
				if ( confirmationRequest === true ) {
					redirectUrl = response.payment_result.redirect_url;
				} else {
					redirectUrl = await confirmationRequest;
				}

				jQuery.blockUI( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6,
					},
				} );

				window.location = redirectUrl;
			} catch ( error ) {
				event.complete( 'fail' );

				jQuery( '.woocommerce-error' ).remove();

				const $container = jQuery(
					'.woocommerce-notices-wrapper'
				).first();

				if ( $container.length ) {
					$container.append(
						jQuery( '<div class="woocommerce-error" />' ).text(
							error.message
						)
					);

					jQuery( 'html, body' ).animate(
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
	}

	attachPaymentRequestButtonEventListeners() {
		if ( this.areListenersInitialized ) {
			return;
		}

		this.areListenersInitialized = true;
		// Block the payment request button as soon as an "input" event is fired, to avoid sync issues
		// when the customer clicks on the button before the debounced event is processed.
		const $quantityInput = jQuery( '.quantity' );
		const handleQuantityChange = () => {
			paymentRequestButtonUi.blockButton();
		};
		$quantityInput.on( 'input', '.qty', handleQuantityChange );
		$quantityInput.on(
			'input',
			'.qty',
			debounce( 250, async () => {
				await applyFilters(
					'wcpay.payment-request.update-button-data',
					Promise.resolve()
				);
				paymentRequestButtonUi.unblockButton();
			} )
		);
	}

	async getCartData() {
		if ( getPaymentRequestData( 'button_context' ) !== 'product' ) {
			return await this.paymentRequestCartApi.getCart();
		}

		// creating a new cart and clearing it afterwards,
		// to avoid scenarios where the stock for a product with limited (or low) availability is added to the cart,
		// preventing other customers from purchasing.
		const temporaryCart = new PaymentRequestCartApi();
		await temporaryCart.createAnonymousCart();

		const cartData = await temporaryCart.addProductToCart();

		// no need to wait for the request to end, it can be done asynchronously.
		// using `.finally( noop )` to avoid annoying IDE warnings.
		temporaryCart.emptyCart().finally( noop );

		return cartData;
	}

	/**
	 * Initialize event handlers and UI state
	 */
	async init() {
		if ( ! this.cachedCartData ) {
			try {
				this.cachedCartData = await this.getCartData();
			} catch ( e ) {
				// if something fails here, we can likely fall back on the `initialProductData`.
			}
		}

		// once cart data has been fetched, we can safely clear cached product data.
		if ( this.cachedCartData ) {
			this.initialProductData = undefined;
		}

		await this.startPaymentRequest();

		// After initializing a new payment request, we need to reset the isPaymentAborted flag.
		this.isPaymentAborted = false;
	}
}
