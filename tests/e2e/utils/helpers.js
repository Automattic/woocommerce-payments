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

// Randomize the email to avoid conflicts.
export const randomizeEmail = ( email ) => {
	return Date.now() + '+' + email;
};

// Get the text value of an HTML input element
export const getInputTextValue = async ( inputName ) => {
	const billingAddress = await page.$( inputName );
	const propertyHandle = await billingAddress.getProperty( 'value' );
	return await propertyHandle.jsonValue();
};
