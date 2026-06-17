/**
 * External dependencies
 */
import type {
	Stripe,
	StripeElements,
	StripeExpressCheckoutElement,
	AvailablePaymentMethods,
} from '@stripe/stripe-js';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';
import {
	shouldUseConfirmationTokens,
	createPaymentCredential,
	getExpressCheckoutData,
	buildStripeElementsOptions,
} from 'wcpay/express-checkout/utils';
import { getSetupFutureUsageForCart } from 'wcpay/express-checkout/utils/subscriptions';
import {
	transformCartDataForDisplayItems,
	transformPrice,
} from 'wcpay/express-checkout/transformers/wc-to-stripe';
import ExpressCheckoutCartApi from 'wcpay/express-checkout/cart-api';
// Side-effect import: registers the WC Subscriptions compatibility filters
// (`total-amount`, `is-cart-eligible`, ...) used below.
import 'wcpay/express-checkout/compatibility/wc-subscriptions';
import {
	appendPaymentMethodIdToForm,
	appendConfirmationTokenToForm,
	appendExpressPaymentTypeToForm,
	appendFraudPreventionTokenInputToForm,
} from 'wcpay/checkout/classic/upe-utils';
import {
	appendFingerprintInputToForm,
	getFingerprint,
} from 'wcpay/checkout/utils/fingerprint';
import { getPaymentMethodsOverride } from 'wcpay/express-checkout/utils/payment-method-overrides';
import { checkAllExpressMethodsAvailability } from 'wcpay/express-checkout/utils/checkPaymentMethodIsAvailable';
import { snakeToCamel } from 'wcpay/express-checkout/constants';
import type WCPayAPI from 'wcpay/checkout/api';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
declare const jQuery: any;

interface CustomPlaceOrderButtonApi {
	validate: () => Promise< { hasError: boolean } >;
	submit: () => void;
}

interface CustomPlaceOrderButtonHandler {
	render: (
		container: HTMLElement,
		wcApi: CustomPlaceOrderButtonApi
	) => Promise< void >;
	cleanup: () => void;
}

declare const wc: {
	customPlaceOrderButton?: {
		register?: (
			gatewayId: string,
			handler: CustomPlaceOrderButtonHandler
		) => void;
	};
} & Record< string, unknown >;

interface PaymentMethodConfig {
	isExpressCheckout?: boolean;
	gatewayId: string;
	stripePaymentMethodType?: string;
}

// Track which gateways have been registered to avoid duplicate registration.
const registeredGateways: Record< string, boolean > = {};

// Cart data from the Store API, refreshed on each `initExpressPaymentMethods` call
// (page load and `updated_checkout`). Needed for the payment sheet's line items and
// for subscription-aware behavior (`setupFutureUsage`, trial cart eligibility).
// eslint-disable-next-line @typescript-eslint/no-explicit-any
let cachedCartData: any = null;
const cartApi = new ExpressCheckoutCartApi();

/**
 * Gets the cart total in smallest currency unit (e.g., cents).
 * Uses the config value as the primary source, with DOM parsing as a fallback.
 */
function getCartTotal(): number {
	// Primary: use the config value (set server-side and updated via AJAX fragments).
	const configTotal = Number( getUPEConfig( 'cartTotal' ) );
	if ( configTotal > 0 ) {
		return configTotal;
	}

	// Fallback: parse from DOM (for edge cases where config isn't updated yet).
	const orderTotalElement = document.querySelector(
		'.woocommerce-checkout-review-order-table .order-total .woocommerce-Price-amount'
	);

	if ( orderTotalElement ) {
		const totalText = orderTotalElement.textContent || '';
		// Remove all non-numeric characters except decimal points and commas.
		// Then normalize: replace comma decimal separators with dots.
		const normalized = totalText
			.replace( /[^\d.,]/g, '' )
			.replace( /,(\d{2})$/, '.$1' ) // Handle "1.234,56" → "1.234.56"
			.replace( /,/g, '' ); // Remove remaining thousand separators
		const total = parseFloat( normalized );

		if ( ! isNaN( total ) && total > 0 ) {
			// Not all currencies have 2 decimals (JPY has 0, KWD has 3) -
			// the exponent Stripe expects is computed server-side.
			const stripeMinorUnit =
				(
					getExpressCheckoutData( 'checkout' ) as {
						stripe_minor_unit?: number;
					} | null
				 )?.stripe_minor_unit ?? 2;

			return Math.round( total * 10 ** stripeMinorUnit );
		}
	}

	return 0;
}

/**
 * Gets the cart total to charge, after compatibility filters (e.g., the recurring
 * total for trial subscription carts with a $0 total due today).
 */
function getEffectiveCartTotal(): number {
	const baseTotal = cachedCartData
		? transformPrice(
				parseInt( cachedCartData.totals.total_price, 10 ) -
					parseInt( cachedCartData.totals.total_refund || 0, 10 ),
				cachedCartData.totals
		  )
		: getCartTotal();

	return applyFilters(
		'wcpay.express-checkout.total-amount',
		baseTotal,
		cachedCartData
	) as number;
}

/**
 * Whether express checkout methods should be offered for the current cart.
 * Carts with a $0 total are not eligible, unless a compatibility filter says
 * otherwise (e.g., free-trial subscription carts, where the customer still
 * needs to authorize the recurring payment).
 */
function isCartEligible(): boolean {
	return applyFilters(
		'wcpay.express-checkout.is-cart-eligible',
		getEffectiveCartTotal() > 0,
		cachedCartData
	) as boolean;
}

/**
 * Hides a payment method from the payment methods list.
 */
function hidePaymentMethod( gatewayId: string ): void {
	const el = document.querySelector< HTMLElement >(
		`.wc_payment_method.payment_method_${ gatewayId }`
	);
	if ( el ) {
		el.style.display = 'none';
	}
}

/**
 * Shows a payment method in the payment methods list.
 */
function showPaymentMethod( gatewayId: string ): void {
	const el = document.querySelector< HTMLElement >(
		`.wc_payment_method.payment_method_${ gatewayId }`
	);
	if ( el ) {
		el.style.display = '';
	}
}

/**
 * Registers a single express payment method with the WC Custom Place Order Button API.
 */
function registerCustomPlaceOrderButton(
	api: WCPayAPI,
	paymentMethodId: string,
	fingerprint: string
): void {
	const config = (
		getUPEConfig( 'paymentMethodsConfig' ) as Record<
			string,
			PaymentMethodConfig
		>
	 )?.[ paymentMethodId ];
	if ( ! config ) {
		return;
	}

	const { gatewayId, stripePaymentMethodType } = config;
	const camelKey = snakeToCamel( paymentMethodId );
	const currency = (
		getUPEConfig( 'currency' ) as string | undefined
	 )?.toLowerCase();

	const useConfirmationTokens = shouldUseConfirmationTokens();

	// Use the shared utility for payment method overrides.
	const paymentMethodOptions =
		getPaymentMethodsOverride( camelKey ).paymentMethods;

	const state: {
		elements: StripeElements | null;
		eceButton: StripeExpressCheckoutElement | null;
	} = {
		elements: null,
		eceButton: null,
	};

	wc.customPlaceOrderButton!.register!( gatewayId, {
		render: async function (
			container: HTMLElement,
			wcApi: CustomPlaceOrderButtonApi
		) {
			if ( ! isCartEligible() ) {
				hidePaymentMethod( gatewayId );
				return;
			}

			try {
				const stripe = ( await api.getStripe() ) as Stripe;
				state.elements = stripe.elements(
					buildStripeElementsOptions( {
						amount: getEffectiveCartTotal(),
						currency: currency!,
						useConfirmationTokens,
						paymentMethodTypes: stripePaymentMethodType
							? [ stripePaymentMethodType ]
							: [],
						captureMethod: getExpressCheckoutData(
							'is_manual_capture'
						)
							? 'manual'
							: undefined,
						setupFutureUsage: getSetupFutureUsageForCart(
							cachedCartData ?? undefined
						),
					} )
				);

				state.eceButton = state.elements.create( 'expressCheckout', {
					buttonType: { applePay: 'plain', googlePay: 'plain' },
					paymentMethods: paymentMethodOptions,
				} );

				state.eceButton.on(
					'ready',
					( {
						availablePaymentMethods,
					}: {
						availablePaymentMethods:
							| AvailablePaymentMethods
							| undefined;
					} ) => {
						if (
							! availablePaymentMethods?.[
								camelKey as keyof AvailablePaymentMethods
							]
						) {
							hidePaymentMethod( gatewayId );
							container.style.display = 'none';
						} else {
							showPaymentMethod( gatewayId );
							container.style.display = '';
						}
					}
				);

				state.eceButton.on( 'click', async ( event ) => {
					const validationResult = await wcApi.validate();
					if ( validationResult.hasError ) {
						return;
					}

					// The checkout form owns address, shipping, and totals -
					// the payment sheet only authorizes the payment, so no
					// shipping address collection is needed here.
					let lineItems;
					try {
						lineItems = cachedCartData
							? transformCartDataForDisplayItems( cachedCartData )
							: undefined;
					} catch {
						lineItems = undefined;
					}

					const storeName = getExpressCheckoutData( 'store_name' ) as
						| string
						| null;

					event.resolve( {
						...( storeName
							? { business: { name: storeName } }
							: {} ),
						emailRequired: true,
						phoneNumberRequired: false,
						shippingAddressRequired: false,
						lineItems,
					} );
				} );

				state.eceButton.on( 'confirm', async () => {
					try {
						const { error: submitError } =
							await state.elements!.submit();
						if ( submitError ) {
							throw new Error( submitError.message );
						}

						const $form = jQuery( 'form.checkout' );

						const credential = await createPaymentCredential(
							stripe,
							state.elements!,
							useConfirmationTokens
						);

						if ( credential.type === 'confirmation_token' ) {
							appendConfirmationTokenToForm(
								$form,
								credential.id
							);
						} else {
							appendPaymentMethodIdToForm( $form, credential.id );
						}

						appendExpressPaymentTypeToForm(
							$form,
							paymentMethodId
						);
						appendFingerprintInputToForm( $form, fingerprint );
						appendFraudPreventionTokenInputToForm( $form );
						wcApi.submit();
					} catch ( error ) {
						const $notices = jQuery(
							'.woocommerce-notices-wrapper'
						).first();
						if ( $notices.length ) {
							$notices.find( '.woocommerce-error' ).remove();
							$notices.append(
								jQuery(
									'<div class="woocommerce-error" />'
								).text( ( error as Error ).message )
							);
							jQuery( 'html, body' ).animate(
								{
									scrollTop: $notices.offset()!.top - 100,
								},
								1000
							);
						}
					}
				} );

				state.eceButton.on( 'loaderror', () => {
					hidePaymentMethod( gatewayId );
					container.style.display = 'none';
				} );

				state.eceButton.mount( container );
			} catch {
				hidePaymentMethod( gatewayId );
			}
		},

		cleanup: function () {
			if ( state.eceButton ) {
				state.eceButton.unmount();
				state.eceButton = null;
			}
			state.elements = null;
		},
	} );
}

/**
 * Main orchestrator: detects availability and registers express payment methods.
 */
async function registerExpressPaymentMethods( api: WCPayAPI ): Promise< void > {
	const currency = ( getUPEConfig( 'currency' ) as string ).toLowerCase();
	const paymentMethodsConfig = getUPEConfig(
		'paymentMethodsConfig'
	) as Record< string, PaymentMethodConfig >;

	// Get all express checkout methods from config (apple_pay, google_pay, amazon_pay).
	const eceExpressMethods = Object.entries( paymentMethodsConfig ).filter(
		( [ , config ] ) => config.isExpressCheckout
	);

	if ( eceExpressMethods.length === 0 ) {
		return;
	}

	// Hide all express methods while checking availability (CSS already hides them,
	// but this handles the case where updated_checkout re-shows them).
	for ( const [ , config ] of eceExpressMethods ) {
		hidePaymentMethod( config.gatewayId );
	}

	try {
		cachedCartData = await cartApi.getCart();
	} catch {
		// Without cart data, the payment sheet has no line items and
		// subscription-aware behavior is skipped - still functional.
		cachedCartData = null;
	}

	if ( ! isCartEligible() ) {
		return;
	}

	// Check device/browser availability via hidden ECE element.
	const availablePaymentMethods = await checkAllExpressMethodsAvailability(
		api,
		getEffectiveCartTotal(),
		currency
	);

	// Get fingerprint for fraud prevention.
	let fingerprint = '';
	try {
		const { visitorId } = await getFingerprint();
		fingerprint = visitorId;
	} catch {
		// Continue without fingerprint.
	}

	for ( const [ paymentMethodId, config ] of eceExpressMethods ) {
		const camelKey = snakeToCamel( paymentMethodId );

		if (
			! availablePaymentMethods[
				camelKey as keyof AvailablePaymentMethods
			]
		) {
			continue;
		}

		// Register with Custom Place Order Button API (once per gateway).
		if ( ! registeredGateways[ config.gatewayId ] ) {
			registerCustomPlaceOrderButton( api, paymentMethodId, fingerprint );
			registeredGateways[ config.gatewayId ] = true;
		}

		showPaymentMethod( config.gatewayId );
	}
}

/**
 * Entry point: initialize express payment methods for classic checkout.
 * Called on page load and on updated_checkout.
 */
export function initExpressPaymentMethods( api: WCPayAPI ): void {
	// Guard: WC Custom Place Order Button API must be available (WC 10.6.0+).
	if (
		typeof wc === 'undefined' ||
		! wc.customPlaceOrderButton ||
		! wc.customPlaceOrderButton.register
	) {
		return;
	}

	// Guard: feature must be enabled.
	if ( ! getUPEConfig( 'isExpressCheckoutInPaymentMethodsEnabled' ) ) {
		return;
	}

	registerExpressPaymentMethods( api );
}
