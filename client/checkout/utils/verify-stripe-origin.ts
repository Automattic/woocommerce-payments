/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

const stripeJsOrigin = 'https://js.stripe.com';

// Returns the Stripe.js tag's resolved `src`, or null when there is no tag or no `src`.
const getStripeJsSrc = (): string | null => {
	// `script#`: another element may share the id.
	const tag =
		document.querySelector< HTMLScriptElement >( 'script#stripe-js' );

	// Read the attribute: `.src` resolves an empty value to the page's base URL.
	return tag?.getAttribute( 'src' ) ? tag.src : null;
};

// Returns why the Stripe.js tag can't be trusted, else null.
const getStripeJsProblem = ( allowNoSrc: boolean ): string | null => {
	const src = getStripeJsSrc();

	if ( src === null ) {
		return allowNoSrc
			? null
			: 'the Stripe.js tag (script#stripe-js) is missing or has no src';
	}

	try {
		if ( new URL( src ).origin === stripeJsOrigin ) {
			return null;
		}
	} catch {
		// An unparsable URL is foreign.
	}

	return `Stripe.js was loaded from an unexpected origin (${ src })`;
};

/**
 * Whether the check is waiting for the Stripe.js tag's `src`: a script optimizer
 * may restore it after another tag has already defined `window.Stripe`.
 */
export const isStripeJsSrcPending = (): boolean =>
	window.wcpayStripeJsOriginCheckDisabled !== true &&
	getStripeJsSrc() === null;

/**
 * Throws when the page's Stripe.js tag is missing or points away from Stripe.
 *
 * Stripe.js is a WordPress script handle, so any code on the site can repoint it
 * (e.g. through `script_loader_src`) at a clone that renders the card fields.
 * This reads the tag, not `window.Stripe`, so it stops a swapped script and
 * nothing else: code that controls the page can still bypass it. The
 * `wcpay_enforce_stripe_js_origin` filter turns it off.
 *
 * @param {Object}  options
 * @param {boolean} options.allowNoSrc Pass a missing tag or one without `src`, for
 *                                     callers that run before a script optimizer
 *                                     may have restored it.
 * @throws {Error} When the Stripe.js tag can't be trusted.
 */
export const assertStripeJsOrigin = ( {
	allowNoSrc = false,
}: { allowNoSrc?: boolean } = {} ): void => {
	if ( window.wcpayStripeJsOriginCheckDisabled === true ) {
		return;
	}

	const problem = getStripeJsProblem( allowNoSrc );
	if ( ! problem ) {
		return;
	}

	// The details go to the console only: shoppers may see the thrown message.
	// eslint-disable-next-line no-console
	console.warn(
		`WooPayments: blocking checkout because ${ problem }. Expected ${ stripeJsOrigin }.`
	);

	throw new Error(
		__( 'Stripe.js provenance check failed.', 'woocommerce-payments' )
	);
};
