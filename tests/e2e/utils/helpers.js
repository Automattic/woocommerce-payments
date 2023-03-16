/**
 * Wait for UI placeholders to finish and UI content is loaded.
 *
 */
const config = require( 'config' );

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

// Check whether specified page exists
export const checkPageExists = async ( slug ) => {
	const wcbPage = await page.goto( config.get( 'url' ) + slug, {
		waitUntil: 'load',
	} );

	if ( 404 === wcbPage.status() ) {
		return Promise.reject();
	}
};
