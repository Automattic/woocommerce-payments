/* global jQuery, wcpayExpressCheckoutParams */
/**
 * External dependencies
 */
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
import './compatibility/wc-product-bundles';
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
import ExpressCheckoutCartApi from './cart-api';
import { getUPEConfig } from 'wcpay/utils/checkout';
import expressCheckoutButtonUi from './button-ui';
import {
	transformCartDataForDisplayItems,
	transformCartDataForShippingRates,
	transformPrice,
} from './transformers/wc-to-stripe';

let cachedCartData = null;
const noop = () => null;
const fetchNewCartData = async () => {
	if ( getExpressCheckoutData( 'button_context' ) !== 'product' ) {
		return await getCartApiHandler().getCart();
	}

	// creating a new cart and clearing it afterward,
	// to avoid scenarios where the stock for a product with limited (or low) availability is added to the cart,
	// preventing other customers from purchasing.
	const temporaryCart = new ExpressCheckoutCartApi();
	temporaryCart.useSeparateCart();

	const cartData = await temporaryCart.addProductToCart();

	// no need to wait for the request to end, it can be done asynchronously.
	// using `.finally( noop )` to avoid annoying IDE warnings.
	temporaryCart.emptyCart().finally( noop );

	return cartData;
};

const getTotalAmount = () => {
	if ( cachedCartData ) {
		return transformPrice(
			parseInt( cachedCartData.totals.total_price, 10 ) -
				parseInt( cachedCartData.totals.total_refund || 0, 10 ),
			cachedCartData.totals
		);
	}

	if (
		getExpressCheckoutData( 'button_context' ) === 'product' &&
		getExpressCheckoutData( 'product' )
	) {
		return getExpressCheckoutData( 'product' )?.total.amount;
	}
};

const getOnClickOptions = () => {
	if ( cachedCartData ) {
		return {
			// pay-for-order should never display the shipping selection.
			shippingAddressRequired:
				getExpressCheckoutData( 'button_context' ) !==
					'pay_for_order' && cachedCartData.needs_shipping,
			shippingRates: transformCartDataForShippingRates( cachedCartData ),
			phoneNumberRequired:
				getExpressCheckoutData( 'checkout' )?.needs_payer_phone ??
				false,
			lineItems: transformCartDataForDisplayItems( cachedCartData ),
		};
	}

	if (
		getExpressCheckoutData( 'button_context' ) === 'product' &&
		getExpressCheckoutData( 'product' )
	) {
		return {
			shippingAddressRequired:
				getExpressCheckoutData( 'product' )?.needs_shipping ?? false,
			phoneNumberRequired:
				getExpressCheckoutData( 'checkout' )?.needs_payer_phone ??
				false,
			lineItems: (
				getExpressCheckoutData( 'product' )?.displayItems ?? []
			).map( ( { label, amount } ) => ( {
				name: label,
				amount,
			} ) ),
		};
	}
};

let elements;

jQuery( ( $ ) => {
	// Don't load if blocks checkout is being loaded.
	if (
		getExpressCheckoutData( 'has_block' ) &&
		getExpressCheckoutData( 'button_context' ) !== 'pay_for_order'
	) {
		return;
	}

	const publishableKey = getExpressCheckoutData( 'stripe' ).publishableKey;

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

	/**
	 * Object to handle Stripe payment forms.
	 */
	const wcpayECE = {
		/**
		 * Abort the payment and display error messages.
		 *
		 * @param {string} message Error message to display.
		 */
		abortPayment: ( message ) => {
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
		 * Starts the Express Checkout Element
		 *
		 * @param {Object} creationOptions ECE initialization options.
		 */
		startExpressCheckoutElement: async ( creationOptions ) => {
			let addToCartPromise = Promise.resolve();
			const stripe = await api.getStripe();
			// https://docs.stripe.com/js/elements_object/create_without_intent
			elements = stripe.elements( {
				mode: 'payment',
				amount: creationOptions.total,
				currency: creationOptions.currency,
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

					// Add products to the cart if everything is right.
					// we are storing the promise to ensure that the "add to cart" call is completed,
					// before the `shippingaddresschange` is triggered when the dialog is opened.
					// Otherwise, it might happen that the `shippingaddresschange` is triggered before the "add to cart" call is done,
					// which can cause errors.
					addToCartPromise = getCartApiHandler().addProductToCart();
					addToCartPromise.finally( () => {
						addToCartPromise = Promise.resolve();
					} );
				}

				const options = getOnClickOptions();
				const shippingOptionsWithFallback =
					// server-side data on the product page initialization doesn't provide any shipping rates.
					! options.shippingRates ||
					// but it can also happen that there are no rates in the array.
					options.shippingRates.length === 0
						? [
								// fallback for initialization (and initialization _only_), before an address is provided by the ECE.
								{
									id: 'pending',
									displayName: __(
										'Pending',
										'woocommerce-payments'
									),
									amount: 0,
								},
						  ]
						: options.shippingRates;

				onClickHandler( event );
				event.resolve( {
					// `options.displayItems`, `options.shippingAddressRequired`, `options.requestPhone`, `options.shippingRates`,
					// are all coming from prior of the initialization.
					// The "real" values will be updated once the button loads.
					// They are preemptively initialized because the `event.resolve({})`
					// needs to be called within 1 second of the `click` event.
					business: {
						name: getExpressCheckoutData( 'store_name' ),
					},
					emailRequired: true,
					...options,
					shippingRates: options.shippingAddressRequired
						? shippingOptionsWithFallback
						: undefined,
					allowedShippingCountries: getExpressCheckoutData(
						'checkout'
					).allowed_shipping_countries,
				} );
			} );

			eceButton.on( 'shippingaddresschange', async ( event ) => {
				await addToCartPromise;
				return shippingAddressChangeHandler( event, elements );
			} );

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
				if (
					getExpressCheckoutData( 'button_context' ) === 'product'
				) {
					// clearing the cart to avoid issues with products with low or limited availability
					// being held hostage by customers cancelling the ECE.
					getCartApiHandler().emptyCart();
				}

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
		},

		/**
		 * Initialize event handlers and UI state
		 */
		init: async () => {
			removeAction(
				'wcpay.express-checkout.update-button-data',
				'automattic/wcpay/express-checkout'
			);

			// on product pages, we should be able to have `getExpressCheckoutData( 'product' )` from the backend,
			// which saves us some AJAX calls.
			if (
				getExpressCheckoutData( 'button_context' ) === 'product' &&
				getExpressCheckoutData( 'product' )?.product_type === 'bundle'
			) {
				// server-side data for bundled products is not reliable.
				wcpayExpressCheckoutParams.product = undefined;
			}

			if ( ! getExpressCheckoutData( 'product' ) && ! cachedCartData ) {
				try {
					cachedCartData = await fetchNewCartData();
				} catch ( e ) {}
			}

			// once (and if) cart data has been fetched, we can safely clear product data from the backend.
			if ( cachedCartData ) {
				wcpayExpressCheckoutParams.product = undefined;
			}

			if ( getExpressCheckoutData( 'button_context' ) === 'product' ) {
				// on product pages, we need to interact with an anonymous cart to check out the product,
				// so that we don't affect the products in the main cart.
				// On cart, checkout, place order pages we instead use the cart itself.
				getCartApiHandler().useSeparateCart();
			}

			const total = getTotalAmount();
			if ( total === 0 ) {
				expressCheckoutButtonUi.hideContainer();
				expressCheckoutButtonUi.getButtonSeparator().hide();
			} else if ( cachedCartData ) {
				// If this is the cart page, or checkout page, or pay-for-order page, we need to request the cart details.
				// but if the data is not available, we can't render the button.
				await wcpayECE.startExpressCheckoutElement( {
					total,
					currency: cachedCartData.totals.currency_code.toLowerCase(),
				} );
			} else if (
				getExpressCheckoutData( 'button_context' ) === 'product' &&
				getExpressCheckoutData( 'product' )
			) {
				await wcpayECE.startExpressCheckoutElement( {
					total,
					currency: getExpressCheckoutData( 'product' )?.currency,
				} );
			} else {
				expressCheckoutButtonUi.hideContainer();
				expressCheckoutButtonUi.getButtonSeparator().hide();
			}

			addAction(
				'wcpay.express-checkout.update-button-data',
				'automattic/wcpay/express-checkout',
				async () => {
					// if the product cannot be added to cart (because of missing variation selection, etc),
					// don't try to add it to the cart to get new data - the call will likely fail.
					if (
						getExpressCheckoutData( 'button_context' ) === 'product'
					) {
						const addToCartButton = $(
							'.single_add_to_cart_button'
						);

						// First check if product can be added to cart.
						if ( addToCartButton.is( '.disabled' ) ) {
							return;
						}
					}

					try {
						expressCheckoutButtonUi.blockButton();

						const prevTotal = getTotalAmount();

						cachedCartData = await fetchNewCartData();

						// We need to re init the payment request button to ensure the shipping options & taxes are re-fetched.
						// The cachedCartData from the Store API will be used from now on,
						// instead of the `product` attributes.
						wcpayExpressCheckoutParams.product = null;

						expressCheckoutButtonUi.unblockButton();

						// since the "total" is part of the initialization of the Stripe elements (and not part of the ECE button),
						// if the totals change, we might need to update it on the element itself.
						const newTotal = getTotalAmount();
						if ( ! elements ) {
							wcpayECE.init();
						} else if ( newTotal !== prevTotal && newTotal > 0 ) {
							elements.update( { amount: newTotal } );
						}

						if ( newTotal === 0 ) {
							expressCheckoutButtonUi.hideContainer();
							expressCheckoutButtonUi.getButtonSeparator().hide();
						} else {
							expressCheckoutButtonUi.showContainer();
							expressCheckoutButtonUi.getButtonSeparator().show();
						}
					} catch ( e ) {
						expressCheckoutButtonUi.hideContainer();
					}
				}
			);
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
