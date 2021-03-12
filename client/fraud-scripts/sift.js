/* eslint-disable camelcase */

const src = 'https://cdn.sift.com/s.js';

export default ( { beacon_key, session_id, user_id } ) => {
	const _sift = ( window._sift = window._sift || [] );
	_sift.push( [ '_setAccount', beacon_key ] );
	_sift.push( [ '_setUserId', user_id ] );
	_sift.push( [ '_setSessionId', session_id ] );
	_sift.push( [ '_trackPageview' ] );

	if ( ! document.querySelector( `[src="${ src }"]` ) ) {
		const script = document.createElement( 'script' );
		script.src = src;
		script.async = true;
		document.body.appendChild( script );
	}
};
