/**
 * The outcome of a successful express checkout authorization, normalized so the
 * environment-specific sinks can serialize it without re-deriving Stripe details.
 *
 * Both dynamic paths (blocks and classic) produce the same logical fields; only
 * the transport differs - see the sinks in `./sinks`.
 */
export interface ExpressPaymentResult {
	/** The Stripe confirmation token id or payment method id. */
	credentialId: string;
	credentialType: 'confirmation_token' | 'payment_method';
	/** The express method, snake_cased: 'apple_pay' | 'google_pay' | 'amazon_pay'. */
	expressPaymentType: string;
	/** Stripe PaymentMethod types for the PaymentIntent (e.g. ['card'], ['amazon_pay']). */
	stripePaymentMethodTypes: string[];
}
