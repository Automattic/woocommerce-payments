/**
 * Stripe's canonical origin. Stripe.js must always be served from here
 * (https://docs.stripe.com/js/including); any other origin is, by Stripe's own
 * rule, never a supported configuration.
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
 * Inspect the document for the Stripe.js <script> tag and verify it was served
 * from Stripe's own origin.
 *
 * WooPayments registers Stripe.js as a WordPress script handle whose URL is
 * mutable by any code on the site (a `script_loader_src` filter, or a
 * deregister/re-register). On a compromised store that handle can be repointed
 * at a look-alike skimmer clone, so the card Elements iframe renders from the
 * attacker's origin. This assertion reads the loaded tag and lets the caller
 * react before `new Stripe()` builds that iframe.
 *
 * The `#stripe-js` id is the WordPress handle's fingerprint (WP appends `-js`
 * to the `stripe` handle), so a repointed handle is read by id regardless of
 * its current src. It is looked up explicitly (not via a selector list) so it
 * always takes precedence: `querySelector` on a comma-separated selector
 * returns the first match in document order, so a legitimate js.stripe.com tag
 * inserted earlier in the DOM could otherwise mask a repointed handle. Only
 * when no handle tag exists do we fall back to any legitimately-loaded
 * js.stripe.com tag (Stripe ships `/v3/`, `/v3/stripe.js`, and named release
 * trains; the origin, not the path, is what we assert).
 *
 * @param doc Document to inspect. Injectable for tests.
 */
export const verifyStripeJsOrigin = (
	doc: Document = document
): StripeOriginResult => {
	const tag =
		doc.querySelector< HTMLScriptElement >( '#stripe-js' ) ??
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
		// Unparseable src: treat as a mismatch rather than trusting it.
		return { ok: false, detectedSrc: tag.src, detectedOrigin: null };
	}
};

/**
 * Asserts that the loaded Stripe.js was served from Stripe's own origin,
 * throwing (and warning) when it was not.
 *
 * Defense-in-depth against a compromised site repointing the mutable `stripe`
 * script handle at a look-alike skimmer clone. This defends the
 * Stripe.js-substitution vector specifically; it is not a general skimmer
 * defense and is bypassable by an attacker with full control of the page.
 *
 * On a mismatch this throws and blocks the payment. The full diagnostics
 * (including the detected src) go to the console warning only; the thrown
 * message may be rendered to shoppers by existing checkout error handling, so
 * it stays generic.
 *
 * @param  options          Options.
 * @param  options.failFast When true (called before `window.Stripe` resolves), a
 *                          missing tag is treated as "still loading" and ignored;
 *                          only a present, wrong-origin tag throws.
 * @throws {Error} When the loaded Stripe.js origin is not Stripe's.
 */
export const assertStripeJsOrigin = ( {
	failFast = false,
}: { failFast?: boolean } = {} ): void => {
	const result = verifyStripeJsOrigin();
	if ( result.ok ) {
		return;
	}

	// Before window.Stripe resolves, "no tag yet" is normal page-load timing,
	// not a mismatch. Only fail fast on a present, wrong-origin tag.
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

	throw new Error( 'Stripe.js provenance check failed.' );
};
