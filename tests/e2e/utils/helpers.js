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

	if ( wcbPage.status() === 404 ) {
		return Promise.reject();
	}
};

/**
 * Retrieves the product price from the current product page.
 *
 * This function assumes that the Puppeteer page object is already navigated to a product page.
 * It extracts the textual content of the element with the class '.woocommerce-Price-amount.amount',
 * which is expected to contain the product's price. The function then removes any non-numeric characters
 * from this text, typically to exclude the currency symbol, and returns just the numeric price.
 *
 * @return {Promise<string>} A promise that resolves to the product price as a string. If the price element
 *                            is not found on the page, the promise is rejected with an error message.
 * @throws {Promise<Error>} If the price element is not found on the page, the function rejects the promise
 *                          with an error message indicating that the price element was not found.
 */
export const getProductPriceFromProductPage = async () => {
	await page.waitForSelector( '.woocommerce-Price-amount.amount', {
		timeout: 5000,
	} );
	const price = await page.evaluate( () => {
		let priceElement = document.querySelector(
			'ins .woocommerce-Price-amount.amount'
		);
		if ( ! priceElement ) {
			// If no discounted price is found, look for the regular price
			priceElement = document.querySelector(
				'.woocommerce-Price-amount.amount'
			);
		}

		if ( priceElement ) {
			let priceText = priceElement.textContent || '';

			// Remove non-numeric characters (excluding the decimal point)
			priceText = priceText.replace( /[^0-9.]/g, '' );

			return priceText;
		}
		return null;
	} );

	if ( price === null ) {
		return Promise.reject(
			new Error( 'Price element not found on the page' )
		);
	}

	return price;
};
