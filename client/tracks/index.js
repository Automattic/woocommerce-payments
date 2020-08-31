/**
 * Checks if site tracking is enabled.
 *
 * @returns {Boolean} True if site tracking is enabled.
 */
function isEnabled() {
	return window.wcTracks.isEnabled;
}

/**
 * Enables site tracking and loads tracking script on the client.
 *
 * It does not change site tracking setting.
 *
 * @returns {Promise} Resolves with true if tracking script is loaded.
 */
async function enable() {
	if ( 'function' !== typeof window.wcTracks.enable ) {
		return false;
	}
	return new Promise( ( resolve ) => window.wcTracks.enable( resolve ) );
}

/**
 * Records site event.
 *
 * @param {String}  eventName       Name of the event.
 * @param {Object?} eventProperties Event properties.
 */
function recordEvent( eventName, eventProperties ) {
	window.wcTracks.recordEvent( eventName, eventProperties );
}

/**
 * Asynchronously records site event.
 *
 * @param {String}   eventName       Event name.
 * @param {Object?}  eventProperties Event properties.
 * @param {Boolean?} [optIn=false]   Opt-in tracking. Pass true to record event if tracking is disabled.
 */
async function recordEventAsync( eventName, eventProperties, optIn = false ) {
	if ( ! isEnabled() && optIn ) {
		await enable();
	}

	recordEvent( eventName, eventProperties );
}

const events = {
	CONNECT_ACCOUNT_CLICKED: 'wcpay_connect_account_clicked',
};

export default {
	isEnabled,
	recordEvent,
	recordEventAsync,
	events,
};
