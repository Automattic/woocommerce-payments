/**
 * Node-side reader for the ECE Stripe proxy recorder.
 *
 * The page-context proxy ships as a mu-plugin
 * (`tests/e2e/mu-plugins/wcpay-ece-test-proxy/proxy.js`), injected inline in
 * <head> before Stripe.js loads. This is the Playwright-side query API specs
 * import to read the `window.__eceStripe` call log the proxy populates.
 */

/**
 * External dependencies
 */
import { Page } from '@playwright/test';

export type EceCall = {
	seq: number;
	instanceId?: number;
	target: string;
	method: string;
	args: any[];
};

/**
 * Reads every recorded Stripe interaction out of page context.
 */
export async function eceStripeCalls( page: Page ): Promise< EceCall[] > {
	return page.evaluate( () => ( window as any ).__eceStripe?.calls ?? [] );
}

/**
 * Filters recorded calls by `target.method` (e.g. `elements.create`) or by
 * bare method name (e.g. `create`).
 */
export const callsTo = ( calls: EceCall[], m: string ): EceCall[] =>
	calls.filter(
		( c ) => `${ c.target }.${ c.method }` === m || c.method === m
	);

/**
 * Returns the most recent call matching `m`, or undefined if none.
 */
export const lastCall = ( calls: EceCall[], m: string ): EceCall | undefined =>
	callsTo( calls, m ).at( -1 );
