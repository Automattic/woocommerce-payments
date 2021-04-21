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
	CONNECT_ACCOUNT_CLICKED: 'wcpay_welcome_finish_setup',
	CONNECT_ACCOUNT_VIEW: 'page_view',
	CONNECT_ACCOUNT_LEARN_MORE: 'wcpay_welcome_learn_more',
};

export default {
	isEnabled,
	recordEvent,
	events,
};
