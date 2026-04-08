/**
 * External dependencies
 */
import { sanitize } from 'dompurify';

// eslint-disable-next-line @typescript-eslint/naming-convention
export const sanitizeHTML = ( html: string ): { __html: string } => ( {
	__html: sanitize( html, {
		ALLOWED_TAGS: [ 'a', 'b', 'em', 'i', 'strong', 'p', 'br' ],
		ALLOWED_ATTR: [ 'target', 'href', 'rel', 'name', 'download' ],
	} ),
} );
