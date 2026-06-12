/**
 * Stripe's canonical origin. Stripe.js must always be served from here
 * (https://docs.stripe.com/js/including); any other origin is, by Stripe's own
 * rule, never a supported configuration.
 */
export const stripeJsOrigin = 'https://js.stripe.com';

export interface StripeOriginResult {
	/** True when the loaded Stripe.js tag was served from stripeJsOrigin. */
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
 * its current src. The fallback finds a legitimately-loaded tag from any
 * js.stripe.com path (Stripe ships `/v3/`, `/v3/stripe.js`, and named release
 * trains; the origin, not the path, is what we assert).
 *
 * @param doc Document to inspect. Injectable for tests.
 */
export const verifyStripeJsOrigin = (
	doc: Document = document
): StripeOriginResult => {
	const tag = doc.querySelector< HTMLScriptElement >(
		'#stripe-js, script[src^="https://js.stripe.com/"]'
	);

	if ( ! tag || ! tag.src ) {
		return { ok: false, detectedSrc: null, detectedOrigin: null };
	}

	try {
		const origin = new URL( tag.src ).origin;
		return {
			ok: origin === stripeJsOrigin,
			detectedSrc: tag.src,
			detectedOrigin: origin,
		};
	} catch {
		// Unparseable src: treat as a mismatch rather than trusting it.
		return { ok: false, detectedSrc: tag.src, detectedOrigin: null };
	}
};
