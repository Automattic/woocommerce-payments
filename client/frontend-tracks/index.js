/**
 * Internal dependencies
 */
import { recordUserEvent } from 'tracks';

if ( window.wcPayFrontendTracks ) {
	const { event, properties } = window.wcPayFrontendTracks;

	recordUserEvent( event, properties );
}
