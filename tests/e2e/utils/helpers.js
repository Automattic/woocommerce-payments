/**
 * External dependencies
 */
import shell from 'shelljs';
const WP_CONTAINER = 'wcp_e2e_wordpress';
const WP_CLI = `docker run --rm --user xfs --volumes-from ${ WP_CONTAINER } --network container:${ WP_CONTAINER } wordpress:cli`;

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

// Helper to run WP-CLI commands
export const runWPCLI = ( command ) => {
	return new Promise( ( resolve, reject ) => {
		shell.exec(
			`${ WP_CLI + ' ' + command }`,
			{ silent: true },
			( err ) => {
				if ( err ) {
					reject( err );
				}
				resolve();
			}
		);
	} );
};
