/**
 * Checks if site tracking is enabled.
 *
 * @returns {boolean} True if site tracking is enabled.
 */
function isEnabled() {
	return window.wcTracks.isEnabled;
}

/**
 * Records site event.
 *
 * @param {string}  eventName       Name of the event.
 * @param {?object} eventProperties Event properties.
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
