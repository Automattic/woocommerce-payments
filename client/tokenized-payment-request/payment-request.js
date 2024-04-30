/* global jQuery, wcpayPaymentRequestParams */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { doAction, addAction } from '@wordpress/hooks';

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
export default class WcpayPaymentRequest {
	/**
	 * Whether the payment was aborted by the customer.
	 */
	paymentAborted = false;

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
		// TODO ~FR: is this creating multiple handlers to events on different `paymentRequest` objects?
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

		// On PDP pages, we need to use an anonymous cart to check out.
		// On cart, checkout, place order pages we instead use the cart itself.
		if ( wcpayPaymentRequestParams.button_context === 'product' ) {
			this.paymentRequestCartApi.createAnonymousCart().then( noop );
		}

		const prButton = this.wcpayApi
			.getStripe()
			.elements()
			.create( 'paymentRequestButton', {
				paymentRequest: paymentRequest,
				style: {
					paymentRequestButton: {
						type: wcpayPaymentRequestParams.button.type,
						theme: wcpayPaymentRequestParams.button.theme,
						height: wcpayPaymentRequestParams.button.height + 'px',
					},
				},
			} );
		paymentRequestButtonUi.showButton( prButton );

		this.attachPaymentRequestButtonEventListeners( paymentRequest );
		addAction(
			'wcpay.payment-request.update-button-data',
			'automattic/wcpay/payment-request',
			async () => {
				const newCartData = await _self.getCartData();

				/**
				 * If the customer aborted the payment request, we need to re init the payment request button to ensure the shipping
				 * options are re-fetched. If the customer didn't abort the payment request, and the product's shipping status is
				 * consistent, we can simply update the payment request button with the new total and display items.
				 */
				if (
					! _self.paymentAborted &&
					( _self.initialProductData.needs_shipping ||
						_self.cachedCartData.needs_shipping ) ===
						newCartData.needs_shipping
				) {
					_self.cachedCartData = newCartData;
					paymentRequest.update( {
						total: {
							label: getPaymentRequestData( 'total_label' ),
							amount: parseInt(
								newCartData.totals.total_price,
								10
							),
						},
						// TODO ~FR: get transform utility
						displayItems: transformCartDataForDisplayItems(
							newCartData
						),
					} );
				} else {
					_self.cachedCartData = newCartData;
					_self.init().then( noop );
				}
			}
		);

		const $addToCartButton = jQuery( '.single_add_to_cart_button' );

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
				if ( $addToCartButton.is( '.wc-variation-is-unavailable' ) ) {
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

			_self.paymentRequestCartApi.addProductToCart();
		} );

		paymentRequest.on( 'cancel', () => {
			_self.paymentAborted = true;
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
						amount: parseInt( cartData.totals.total_price, 10 ),
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
						amount: parseInt( cartData.totals.total_price, 10 ),
					},
					displayItems: transformCartDataForDisplayItems( cartData ),
				} );
				_self.cachedCartData = cartData;
			} catch ( error ) {
				event.updateWith( { status: 'fail' } );
			}
		} );

		paymentRequest.on( 'paymentmethod', async ( event ) => {
			// TODO ~FR: this works for PDPs - need to handle checkout scenarios for pay-for-order, cart, checkout.
			try {
				const response = await _self.paymentRequestCartApi.placeOrder(
					transformStripePaymentMethodForStoreApi( event )
				);

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
			debounce( 250, () => {
				doAction( 'wcpay.payment-request.update-button-data' );
				paymentRequestButtonUi.unblockButton();
			} )
		);
	}

	async getCartData() {
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
	async init( { refresh = false } = {} ) {
		if ( ! this.cachedCartData || refresh ) {
			try {
				this.cachedCartData = await this.getCartData();
			} catch ( e ) {}
		}

		this.startPaymentRequest().then( noop );

		// After initializing a new payment request, we need to reset the paymentAborted flag.
		this.paymentAborted = false;

		// once cart data has been fetched, we can safely clear cached product data.
		if ( this.cachedCartData ) {
			this.initialProductData = undefined;
		}
	}
}
