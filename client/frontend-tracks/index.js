/**
 * Internal dependencies
 */
import { recordUserEvent } from 'tracks';

if ( window.wcPayFrontendTracks && window.wcPayFrontendTracks.length ) {
	for ( const track of window.wcPayFrontendTracks ) {
		recordUserEvent( track.event, track.properties );
	}

	window.wcPayFrontendTracks = [];
}
