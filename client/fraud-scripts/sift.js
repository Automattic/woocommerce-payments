const src = 'https://cdn.sift.com/s.js';

export default ( {
	beacon_key: beaconKey,
	session_id: sessionId,
	user_id: userId,
} ) => {
	const _sift = ( window._sift = window._sift || [] );
	_sift.push( [ '_setAccount', beaconKey ] );
	_sift.push( [ '_setUserId', userId ] );
	_sift.push( [ '_setSessionId', sessionId ] );
	_sift.push( [ '_trackPageview' ] );

	if ( ! document.querySelector( `[src="${ src }"]` ) ) {
		const script = document.createElement( 'script' );
		script.src = src;
		script.async = true;
		document.body.appendChild( script );
	}
};
