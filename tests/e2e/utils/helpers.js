const os = require( 'os' );

/**
 * Wait for UI placeholders to finish and UI content is loaded.
 *
 */
export const uiLoaded = async () => {
	await page.waitForFunction(
		() => ! Boolean( document.querySelector( '.is-loadable-placeholder' ) )
	);
};

// Conditionally determine whether or not to skip a test suite
export const describeif = ( condition ) =>
	condition ? describe : describe.skip;

// Save full page screenshot to file.
export const takeScreenshot = ( name ) => {
	return page.screenshot( {
		path: `./screenshots/${ name }.png`,
		fullPage: true,
	} );
};

/**
 * Returns the number of lines in a given string
 * based on OS-specific newline character.
 *
 * @param {string} str The string
 * @return {number} Number of lines in string
 */
export const countLines = ( str ) => {
	const lines = str.split( os.EOL );
	const numLines = lines.length;
	return numLines;
};

/**
 * Returns the line on given position in a string.
 *
 * @param {string} str The string
 * @param {number} lineNum Line number
 * @return {string} The text at given line number
 */
export const getTextAtLine = ( str, lineNum ) => {
	const lines = str.split( os.EOL );
	return lines[ lineNum - 1 ];
};
