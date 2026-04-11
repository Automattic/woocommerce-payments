/**
 * Wait for a given number of milliseconds.
 *
 * @param { number } ms The number of milliseconds to wait.
 * @return {Promise<unknown>} A promise that resolves after the given number of milliseconds.
 */
export const waitMilliseconds = ( ms ) => {
	return new Promise( ( resolve ) => {
		setTimeout( resolve, ms );
	} );
};

/**
 * Wait for a selector to be available in the DOM.
 *
 * In the context of the direct checkout flow, we use this to wait for
 * a button to render, that's why the default timeout is set to 2000ms.
 *
 * @param {string} selector The CSS selector to wait for.
 * @param {Function} callback The callback function to be called when the selector is available.
 * @param {integer} timeout The timeout in milliseconds.
 */
export const waitForSelector = ( selector, callback, timeout = 2000 ) => {
	const startTime = Date.now();
	const checkElement = () => {
		if ( Date.now() - startTime > timeout ) {
			return;
		}

		const element = document.querySelector( selector );
		if ( element ) {
			callback( element );
		} else {
			requestAnimationFrame( checkElement );
		}
	};

	requestAnimationFrame( checkElement );
};
