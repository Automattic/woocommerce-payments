/**
 * Checks if site tracking is enabled.
 *
 * @return {boolean} True if site tracking is enabled.
 */
function isEnabled() {
	return window.wcTracks.isEnabled;
}

/**
 * Records site event.
 *
 * @param {string}  eventName       Name of the event.
 * @param {Object?} eventProperties Event properties.
 */
function recordEvent( eventName, eventProperties ) {
	const recordFunction =
		window.wc?.tracks?.recordEvent ?? window.wcTracks.recordEvent;
	recordFunction( eventName, eventProperties );
}

const events = {
	CONNECT_ACCOUNT_CLICKED: 'wcpay_connect_account_clicked',
};

export default {
	isEnabled,
	recordEvent,
	events,
};
