/**
 * Wait for UI placeholders to finish and UI content is loaded.
 *
 * Eventually this will be included in the @woocommerce/e2e-utils package and will be moved there.
 * See: https://github.com/woocommerce/woocommerce/issues/29751
 */
export const uiWCAdminLoaded = async () => {
	await page.waitForFunction(
		() => ! Boolean( document.querySelector( '.is-loadable-placeholder' ) )
	);
};
