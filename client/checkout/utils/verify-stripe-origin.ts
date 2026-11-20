/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

const stripeJsOrigin = 'https://js.stripe.com';

// Returns the Stripe.js tag's `src` when it points away from Stripe, else null.
const getForeignStripeJsSrc = (): string | null => {
	// `script#`: another element may share the id. Script optimizers move `src`
	// to a data attribute, so a tag without `src` passes.
	const src =
		document.querySelector< HTMLScriptElement >( 'script#stripe-js' )?.src;
	if ( ! src ) {
		return null;
	}

	try {
		return new URL( src ).origin === stripeJsOrigin ? null : src;
	} catch {
		return src;
	}
};

/**
 * Throws when the page's Stripe.js tag points away from Stripe.
 *
 * Stripe.js is a WordPress script handle, so any code on the site can repoint it
 * (e.g. through `script_loader_src`) at a clone that renders the card fields.
 * This reads the tag, not `window.Stripe`, so it stops a swapped script and
 * nothing else: code that controls the page can still bypass it. The
 * `wcpay_enforce_stripe_js_origin` filter turns it off.
 *
 * @throws {Error} When the Stripe.js tag has a `src` outside js.stripe.com.
 */
export const assertStripeJsOrigin = (): void => {
	if ( window.wcpayStripeJsOriginCheckDisabled === true ) {
		return;
	}

	const foreignSrc = getForeignStripeJsSrc();
	if ( ! foreignSrc ) {
		return;
	}

	// The detected source goes to the console only: shoppers may see the thrown message.
	// eslint-disable-next-line no-console
	console.warn(
		`WooPayments: blocking checkout because Stripe.js was loaded from an unexpected origin (${ foreignSrc }). Expected ${ stripeJsOrigin }.`
	);

	throw new Error(
		__( 'Stripe.js provenance check failed.', 'woocommerce-payments' )
	);
};
