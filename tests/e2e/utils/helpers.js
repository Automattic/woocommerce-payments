/**
 * Wait for UI placeholders to finish and UI content is loaded.
 *
 */
export const uiLoaded = async () => {
	await page.waitForFunction(
		() => ! Boolean( document.querySelector( '.is-loadable-placeholder' ) )
	);
};
