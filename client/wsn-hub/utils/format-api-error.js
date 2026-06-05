/**
 * Format an API error into a merchant-friendly message.
 *
 * WHY THIS HELPER EXISTS:
 * A sweep of the WSN Hub call sites flagged two systemic problems with the
 * common `catch ( e ) { setError( e?.message ) }` pattern:
 *
 *   1. On HTML 5xx responses (e.g., a PHP fatal that bypasses the REST stack
 *      and returns a raw error page), `response.json()` throws a SyntaxError
 *      whose `message` looks like "Unexpected token '<', "<!DOCTYPE "...".
 *      Surfacing that string to merchants leaks framework internals and is
 *      meaningless to them. We normalise these into a generic server error.
 *
 *   2. WordPress REST validation failures (HTTP 422) carry per-field error
 *      details in `error.data.params` — reading only `error.message` drops
 *      those field-level messages entirely, so merchants see "Invalid
 *      parameter(s)." with no indication of WHICH field is wrong. We flatten
 *      the params map into the surfaced string.
 *
 * Centralising this logic also gives us a single place to handle session
 * expiry (401 / invalid nonce) and permission (403) errors consistently
 * across every tab in the WSN Hub.
 */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Convert any thrown value from a REST/fetch call into a string safe to
 * display to a merchant.
 *
 * @param {*} error The caught error. May be a TypeError (network), a
 *                  SyntaxError (HTML body), a WP REST error object with
 *                  `data.status` / `data.params` / `code`, or anything else.
 * @return {string} Merchant-friendly message.
 */
export function formatApiError( error ) {
	// Network failures surface as TypeError ("Failed to fetch") in browsers,
	// and a missing message generally means we caught something opaque
	// (undefined, null, a bare string thrown, etc.).
	if ( error instanceof TypeError || ! error || ! error.message ) {
		return __(
			'Network problem — check your connection and try again.',
			'woocommerce-payments'
		);
	}

	const status = error?.data?.status;
	const code = error?.code;
	const message = error?.message;

	// 5xx responses, or a SyntaxError raised while parsing a non-JSON body
	// returned from a 5xx. The "Unexpected token" sniff catches the latter
	// case where `data.status` is unavailable because the JSON parse blew up
	// before the REST envelope could be read.
	if (
		( typeof status === 'number' && status >= 500 ) ||
		( typeof message === 'string' &&
			message.includes( 'Unexpected token' ) )
	) {
		return __(
			'Server error — please try again or contact support.',
			'woocommerce-payments'
		);
	}

	// Session expired / nonce rotated out from under the page.
	if ( status === 401 || code === 'rest_cookie_invalid_nonce' ) {
		return __(
			'Your session expired — please refresh the page.',
			'woocommerce-payments'
		);
	}

	// Capability check failed.
	if ( status === 403 || code === 'rest_forbidden' ) {
		return __(
			'You do not have permission to make this change.',
			'woocommerce-payments'
		);
	}

	// Validation errors: flatten the per-field params map into the message.
	if (
		status === 422 &&
		error?.data &&
		Reflect.has( error.data, 'params' ) &&
		error.data.params &&
		typeof error.data.params === 'object'
	) {
		const params = error.data.params;
		// Own-property iteration only — `Reflect.has` is true for inherited
		// properties too, which would surface prototype-chain garbage in
		// the merchant-facing message if `params` ever has a polluted
		// prototype. `Object.entries` returns own enumerable string-keyed
		// pairs only.
		const fieldParts = Object.entries( params ).map(
			( [ fieldName, value ] ) => `${ fieldName }: ${ value }`
		);

		const prefix = message ? `${ message } ` : '';
		if ( fieldParts.length > 0 ) {
			return `${ prefix }(${ fieldParts.join( ', ' ) })`.trim();
		}

		// 422 with no params — fall back to the message if we have one.
		if ( message ) {
			return message;
		}
	}

	return (
		message ??
		__( 'Something went wrong — please try again.', 'woocommerce-payments' )
	);
}
