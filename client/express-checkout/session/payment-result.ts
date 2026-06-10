/**
 * The Stripe credential produced by authorizing an express payment - the part
 * every path shares, regardless of how it reaches WooCommerce.
 */
export interface ExpressPaymentCredential {
	/** The Stripe confirmation token id or payment method id. */
	credentialId: string;
	credentialType: 'confirmation_token' | 'payment_method';
}

/**
 * A credential plus the method identity the per-method "dynamic place order
 * button" paths carry. The standalone paths derive that identity from the
 * wallet event instead, so they work with the bare {@link ExpressPaymentCredential}.
 *
 * The sinks in `./sinks` map one of these to each environment's transport.
 */
export interface ExpressPaymentResult extends ExpressPaymentCredential {
	/** The express method, snake_cased: 'apple_pay' | 'google_pay' | 'amazon_pay'. */
	expressPaymentType: string;
	/** Stripe PaymentMethod types for the PaymentIntent (e.g. ['card'], ['amazon_pay']). */
	stripePaymentMethodTypes: string[];
}
