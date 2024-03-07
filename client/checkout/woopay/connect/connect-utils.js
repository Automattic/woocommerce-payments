export const INJECTED_STATE = {
	NOT_INJECTED: 0,
	INJECTING: 1,
	INJECTED: 2,
};

/**
 * Set the injected state of the WooPayConnectIframe.
 *
 * @param {int} state The injected state to set.
 */
export function setConnectIframeInjectedState( state ) {
	if ( ! window.WooPayConnect ) {
		window.WooPayConnect = {};
	}

	window.WooPayConnect.iframeInjectedState = state;
}

/**
 * Get the injected state of the WooPayConnectIframe.
 *
 * @return {int} The injected state of the WooPayConnectIframe.
 */
export function getConnectIframeInjectedState() {
	return (
		window?.WooPayConnect?.iframeInjectedState ||
		INJECTED_STATE.NOT_INJECTED
	);
}
