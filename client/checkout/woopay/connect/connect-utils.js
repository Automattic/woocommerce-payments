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

/**
 * Set the postMessage timeout for WooPayConnect.
 * This is the amount of time to wait for a response from the WooPayConnectIframe.
 *
 * @param {int} milliseconds The postMessage timeout in milliseconds.
 */
export function setPostMessageTimeout( milliseconds ) {
	if ( ! window.WooPayConnect ) {
		window.WooPayConnect = {};
	}

	window.WooPayConnect.postMessageTimeout = milliseconds;
}

/**
 * Get the postMessage timeout for WooPayConnect. If not set, the default is 5000 milliseconds.
 *
 * @return {int|number} The postMessage timeout in milliseconds.
 */
export function getPostMessageTimeout() {
	return window?.WooPayConnect?.postMessageTimeout || 5000;
}
