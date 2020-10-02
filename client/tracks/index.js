/**
 * Checks if site tracking is enabled.
 *
 * @returns {Boolean} True if site tracking is enabled.
 */
function isEnabled() {
	return window.wcTracks.isEnabled;
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

const events = {
	CONNECT_ACCOUNT_CLICKED: 'wcpay_connect_account_clicked',
};

export default {
	isEnabled,
	recordEvent,
	events,
};
