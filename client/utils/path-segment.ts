/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export class InvalidPathSegmentError extends Error {
	name = 'InvalidPathSegmentError';

	code = 'wcpay_invalid_path_segment';
}

/**
 * Encodes an ID for use as a REST API path segment.
 *
 * IDs can come from the page URL, so they must not be able to move the request outside the
 * resource's path. The format is not otherwise restricted because Stripe IDs are opaque.
 * Encoding stops the browser from resolving `/`, `\`, `?`, `#` and `%`, but `.` and `..` survive
 * it and would still be resolved as dot segments. WordPress decodes `%2F` back to `/` before
 * routing, so an ID containing `/` can still reach a sub-route of the same resource.
 *
 * @param  id The ID to encode.
 *
 * @throws {InvalidPathSegmentError} When the ID cannot be used as a path segment.
 */
export const encodePathSegment = ( id: unknown ): string => {
	if (
		( typeof id !== 'string' && typeof id !== 'number' ) ||
		[ '', '.', '..' ].includes( String( id ) )
	) {
		throw new InvalidPathSegmentError(
			__( 'Invalid identifier.', 'woocommerce-payments' )
		);
	}

	return encodeURIComponent( id );
};
