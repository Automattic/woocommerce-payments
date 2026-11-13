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
