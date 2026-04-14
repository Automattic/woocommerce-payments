/**
 * External dependencies
 */
import DOMPurify from 'dompurify';

// eslint-disable-next-line @typescript-eslint/naming-convention
export const sanitizeHTML = ( html: string ): { __html: string } => ( {
	__html: DOMPurify.sanitize( html, {
		ALLOWED_TAGS: [ 'a', 'b', 'em', 'i', 'strong', 'p', 'br' ],
		ALLOWED_ATTR: [ 'target', 'href', 'rel', 'name', 'download' ],
	} ),
} );
