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
	window.wcTracks.recordEvent( eventName, eventProperties );
}

const events = {
	CONNECT_ACCOUNT_CLICKED: 'wcpay_connect_account_clicked',
	CONNECT_ACCOUNT_VIEW: 'wcadmin_page_view',
	CONNECT_ACCOUNT_LEARN_MORE: 'wcadmin_wcpay_welcome_learn_more',
};

export default {
	isEnabled,
	recordEvent,
	events,
};
