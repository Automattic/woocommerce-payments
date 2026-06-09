type PaymentMethodMode = 'always' | 'auto' | 'never';

interface PaymentMethodsOverride {
	paymentMethods: Record< string, PaymentMethodMode >;
}

/**
 * Returns Stripe ECE payment method overrides that enable only the specified
 * express payment method and disable all others.
 *
 * Apple Pay and Google Pay use 'always' to force availability.
 * Amazon Pay uses 'auto' because Stripe doesn't support 'always' for it.
 */
export const getPaymentMethodsOverride = (
	enabledPaymentMethod: string
): PaymentMethodsOverride => {
	const allDisabled: Record< string, PaymentMethodMode > = {
		applePay: 'never',
		googlePay: 'never',
		amazonPay: 'never',
		link: 'never',
		paypal: 'never',
		klarna: 'never',
	};

	const enabledParam: PaymentMethodMode = [
		'applePay',
		'googlePay',
	].includes( enabledPaymentMethod )
		? 'always'
		: 'auto';

	return {
		paymentMethods: {
			...allDisabled,
			[ enabledPaymentMethod ]: enabledParam,
		},
	};
};

interface ButtonOptions {
	buttonHeight: number;
	buttonTheme: Record< string, string >;
	[ key: string ]: unknown;
}

/**
 * Visual adjustments to horizontally align the ECE buttons.
 *
 * Corrects small height differences between Apple Pay and Google Pay
 * caused by button themes and borders, and clamps the result to Stripe's
 * allowed range (40–55 px).
 */
export const adjustButtonHeights = (
	buttonOptions: ButtonOptions,
	expressPaymentMethod: string
): ButtonOptions => {
	const adjusted = { ...buttonOptions };

	// Apple Pay has a nearly imperceptible height difference. We increase it by 0.4px here.
	if ( adjusted.buttonTheme.applePay === 'black' ) {
		if ( expressPaymentMethod === 'applePay' ) {
			adjusted.buttonHeight = adjusted.buttonHeight + 0.4;
		}
	}

	// GooglePay with the white theme has a 2px height difference due to its border.
	if (
		expressPaymentMethod === 'googlePay' &&
		adjusted.buttonTheme.googlePay === 'white'
	) {
		adjusted.buttonHeight = adjusted.buttonHeight - 2;
	}

	// Clamp the button height to the allowed range 40px to 55px.
	adjusted.buttonHeight = Math.max(
		40,
		Math.min( adjusted.buttonHeight, 55 )
	);
	return adjusted;
};
