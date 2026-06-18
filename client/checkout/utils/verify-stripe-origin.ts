/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Stripe's canonical origin. Stripe.js is only ever served from here
 * (https://docs.stripe.com/js/including); any other origin is unsupported.
 */
// eslint-disable-next-line @typescript-eslint/naming-convention -- Module-level constant.
export const STRIPE_JS_ORIGIN = 'https://js.stripe.com';

export interface StripeOriginResult {
	/** True when the loaded Stripe.js tag was served from STRIPE_JS_ORIGIN. */
	ok: boolean;
	/** The src of the inspected tag, or null when no tag was found. */
	detectedSrc: string | null;
	/** The origin of the inspected tag, or null when absent/unparseable. */
	detectedOrigin: string | null;
}

/**
 * Inspect the loaded Stripe.js <script> tag and check it came from Stripe's
 * origin.
 *
 * WooPayments enqueues Stripe.js as a WordPress handle whose URL any site code
 * can repoint (e.g. via `script_loader_src`); on a compromised store that lets
 * a skimmer clone render the card iframe from its own origin. We read the tag
 * before `new Stripe()` so the caller can block first.
 *
 * The `#stripe-js` handle is matched first and explicitly — tag-qualified as
 * `script#stripe-js`, so a non-script element sharing the id can't divert the
 * lookup — so a repointed handle always wins over a legitimate js.stripe.com tag
 * elsewhere in the DOM. Only without a handle tag do we fall back to any
 * js.stripe.com tag — the origin is asserted, not the path.
 *
 * @param doc Document to inspect. Injectable for tests.
 */
export const verifyStripeJsOrigin = (
	doc: Document = document
): StripeOriginResult => {
	const tag =
		doc.querySelector< HTMLScriptElement >( 'script#stripe-js' ) ??
		// Fallback presence check: the `src^=` filter already pins the origin, so
		// a match here is always ok — it just confirms Stripe.js is present when
		// no `#stripe-js` handle exists (a total absence still fails closed below).
		doc.querySelector< HTMLScriptElement >(
			'script[src^="https://js.stripe.com/"]'
		);

	if ( ! tag || ! tag.src ) {
		return { ok: false, detectedSrc: null, detectedOrigin: null };
	}

	try {
		const origin = new URL( tag.src ).origin;
		return {
			ok: origin === STRIPE_JS_ORIGIN,
			detectedSrc: tag.src,
			detectedOrigin: origin,
		};
	} catch {
		// Unparseable src: treat as a mismatch.
		return { ok: false, detectedSrc: tag.src, detectedOrigin: null };
	}
};

/**
 * Assert the loaded Stripe.js came from Stripe's origin; throw (and warn) if not.
 *
 * Defense-in-depth against a compromised site repointing the `stripe` handle at
 * a skimmer clone. It checks the loaded tag's origin, not the `window.Stripe`
 * identity, so it catches the Stripe.js-substitution vector but not an attacker
 * who proxies the real Stripe object; bypassable with full control of the page.
 *
 * The thrown message stays generic — checkout error handling may show it to
 * shoppers — while full diagnostics (the detected src) go to the console warning.
 *
 * @param  options          Options.
 * @param  options.failFast Before `window.Stripe` resolves: ignore a missing tag
 *                          ("still loading"); only a present wrong-origin tag throws.
 * @throws {Error} When the loaded Stripe.js origin is not Stripe's.
 */
export const assertStripeJsOrigin = ( {
	failFast = false,
}: { failFast?: boolean } = {} ): void => {
	const result = verifyStripeJsOrigin();
	if ( result.ok ) {
		return;
	}

	// A missing tag before window.Stripe resolves is normal load timing, not a
	// mismatch; only a present, wrong-origin tag fails fast.
	if ( failFast && result.detectedSrc === null ) {
		return;
	}

	const reason =
		result.detectedSrc === null
			? 'no Stripe.js script tag was found'
			: `Stripe.js was loaded from an unexpected origin (${ result.detectedSrc })`;

	// eslint-disable-next-line no-console
	console.warn(
		`WooPayments: blocking checkout — ${ reason }. Expected Stripe.js from ${ STRIPE_JS_ORIGIN }.`
	);

	throw new Error(
		__( 'Stripe.js provenance check failed.', 'woocommerce-payments' )
	);
};
