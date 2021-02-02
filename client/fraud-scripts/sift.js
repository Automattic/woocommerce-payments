/* eslint-disable camelcase */

export const src = 'https://cdn.sift.com/s.js';

export const init = ( { beacon_key, session_id, user_id } ) => {
	const _sift = ( window._sift = window._sift || [] );
	_sift.push( [ '_setAccount', beacon_key ] );
	_sift.push( [ '_setUserId', user_id ] );
	_sift.push( [ '_setSessionId', session_id ] );
	_sift.push( [ '_trackPageview' ] );
	// alert('--- SIFT tracking ---\nUser ID: ' + user_id + '\nSession ID: ' + session_id);
};
