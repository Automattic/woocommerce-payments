/**
 * Internal dependencies
 */

import { getConfig } from 'utils/checkout';

/**
 * Checks whether we're in a preview context.
 *
 * @return {boolean} Whether we're in a preview context.
 */
export const isPreviewing = () => {
	const searchParams = new URLSearchParams( window.location.search );

	// Check for the URL parameter used in the iframe of the customize.php page
	// and for the is_preview() value for posts.
	return (
		null !== searchParams.get( 'customize_messenger_channel' ) ||
		getConfig( 'isPreview' )
	);
};
