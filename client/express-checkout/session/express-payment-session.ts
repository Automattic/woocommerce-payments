/**
 * External dependencies
 */
import type { Stripe, StripeElements } from '@stripe/stripe-js';

/**
 * Internal dependencies
 */
import {
	buildStripeElementsOptions,
	createPaymentCredential,
} from 'wcpay/express-checkout/utils';
import { getSetupFutureUsageForCart } from 'wcpay/express-checkout/utils/subscriptions';
import { transformCartDataForDisplayItems } from 'wcpay/express-checkout/transformers/wc-to-stripe';
import { validateElements } from 'wcpay/checkout/utils/validate-elements';
import type { ExpressPaymentResult } from './payment-result';

// The Store API cart shape, kept loose - only the fields the transformers and
// subscription helpers read are relevant here.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type CartData = any;

export interface ExpressPaymentSessionConfig {
	/** camelCase method key: 'applePay' | 'googlePay' | 'amazonPay'. */
	method: string;
	/** snake_cased method key, sent to the server: 'apple_pay' | ... */
	expressPaymentType: string;
	/** Stripe PaymentMethod type for the PaymentIntent ('card', 'amazon_pay'). */
	stripePaymentMethodType: string;
	/** Cart total in the smallest currency unit, already filtered/transformed by the caller. */
	amount: number;
	currency: string;
	useConfirmationTokens: boolean;
	isManualCapture: boolean;
	/** Store API cart data, for line items and subscription detection. */
	cartData?: CartData | null;
	storeName?: string | null;
	needsPayerPhone?: boolean;
}

interface ClickResolution {
	emailRequired: true;
	phoneNumberRequired: boolean;
	// The checkout form owns address, shipping, and totals when the method is a
	// row in the payment methods list, so the wallet sheet never collects shipping.
	shippingAddressRequired: false;
	lineItems?: ReturnType< typeof transformCartDataForDisplayItems >;
	business?: { name: string };
}

/**
 * Owns the Stripe Express Checkout Element lifecycle that the two "dynamic place
 * order button" paths share: the Elements options, the payment-sheet resolution
 * on click, and turning a confirm into a normalized {@link ExpressPaymentResult}.
 *
 * It is deliberately environment-agnostic - it neither mounts the element (React
 * vs. imperative) nor delivers the result to WooCommerce (that is the sink's job).
 * The caller resolves the amount/cart up front and the session is a pure function
 * of its config from there.
 */
export class ExpressPaymentSession {
	private readonly config: ExpressPaymentSessionConfig;

	public constructor( config: ExpressPaymentSessionConfig ) {
		this.config = config;
	}

	/**
	 * Options for `stripe.elements()` / the React `<Elements>` provider.
	 */
	public getElementsOptions(): ReturnType<
		typeof buildStripeElementsOptions
	> {
		const { amount, currency, useConfirmationTokens, isManualCapture } =
			this.config;

		return buildStripeElementsOptions( {
			amount,
			currency,
			useConfirmationTokens,
			paymentMethodTypes: this.getPaymentMethodTypes(),
			captureMethod: isManualCapture ? 'manual' : undefined,
			setupFutureUsage: getSetupFutureUsageForCart(
				this.config.cartData ?? undefined
			),
		} );
	}

	/**
	 * The payment-sheet configuration passed to the Express Checkout Element's
	 * `click` event `resolve()`.
	 */
	public buildClickResolution(): ClickResolution {
		const resolution: ClickResolution = {
			emailRequired: true,
			phoneNumberRequired: this.config.needsPayerPhone ?? false,
			shippingAddressRequired: false,
			lineItems: this.getLineItems(),
		};

		if ( this.config.storeName ) {
			resolution.business = { name: this.config.storeName };
		}

		return resolution;
	}

	/**
	 * Submits the elements and creates the payment credential.
	 *
	 * @throws The Stripe error if element validation or credential creation fails.
	 *         Callers map this to their environment's error contract via the sink.
	 */
	public async confirm(
		stripe: Stripe,
		elements: StripeElements
	): Promise< ExpressPaymentResult > {
		await validateElements( elements );

		const credential = await createPaymentCredential(
			stripe,
			elements,
			this.config.useConfirmationTokens
		);

		return {
			credentialId: credential.id,
			credentialType: credential.type,
			expressPaymentType: this.config.expressPaymentType,
			stripePaymentMethodTypes: this.getPaymentMethodTypes(),
		};
	}

	// A method may have no dedicated Stripe type (the server emits one for the
	// express methods, but it can be absent), in which case Stripe is left to
	// infer the types - hence the empty-string filter rather than `['']`.
	private getPaymentMethodTypes(): string[] {
		return [ this.config.stripePaymentMethodType ].filter(
			Boolean
		) as string[];
	}

	private getLineItems():
		| ReturnType< typeof transformCartDataForDisplayItems >
		| undefined {
		if ( ! this.config.cartData ) {
			return undefined;
		}

		try {
			return transformCartDataForDisplayItems( this.config.cartData );
		} catch {
			return undefined;
		}
	}
}
