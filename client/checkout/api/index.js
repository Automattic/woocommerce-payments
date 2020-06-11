export default class WCPayAPI {
	constructor( options ) {
		this.options = options;

		const {
			publishableKey,
			accountId,
		} = options;

		this.stripe = new Stripe( publishableKey, {
			stripeAccount: accountId,
		} );
	}

	getStripe() {
		return this.stripe;
	}

	createPaymentMethod( cardElement, billingDetails ) {
		var args = {
			type: 'card',
			card: cardElement,
			// eslint-disable-next-line camelcase
			billing_details: loadBillingDetails(),
		};

		stripe.createPaymentMethod( paymentMethodArgs )
	}
}
