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
