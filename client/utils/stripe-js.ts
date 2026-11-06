/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';

const defaultStripeJsUrl = 'https://js.stripe.com/v3/';

// The same patterns `@stripe/stripe-js` uses to find an existing tag, so `loadStripe()`
// reuses whatever tag we add instead of adding its own.
const v3UrlRegex = /^https:\/\/js\.stripe\.com\/v3\/?(\?.*)?$/;
const stripeJsUrlRegex =
	/^https:\/\/js\.stripe\.com\/(v3|[a-z]+)\/stripe\.js(\?.*)?$/;

export const getStripeJsUrl = (): string => {
	const checkoutUrl = getUPEConfig( 'stripeJsUrl' );
	if ( checkoutUrl ) {
		return checkoutUrl;
	}

	if ( typeof wcpaySettings !== 'undefined' && wcpaySettings.stripeJsUrl ) {
		return wcpaySettings.stripeJsUrl;
	}

	return defaultStripeJsUrl;
};

export const hasStripeJsScript = (): boolean =>
	Array.from(
		document.querySelectorAll< HTMLScriptElement >(
			'script[src^="https://js.stripe.com/"]'
		)
	).some(
		( script ) =>
			v3UrlRegex.test( script.src ) || stripeJsUrlRegex.test( script.src )
	);

export const ensureStripeJsScript = (): void => {
	if ( hasStripeJsScript() ) {
		return;
	}

	const script = document.createElement( 'script' );
	script.src = getStripeJsUrl();
	script.async = true;
	document.body.appendChild( script );
};
