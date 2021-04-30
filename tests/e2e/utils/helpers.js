/**
 * Wait for UI placeholders to finish and UI content is loaded.
 *
 */
export const elementUnblocked = async () => {
	await page.waitForFunction(
		() => ! Boolean( document.querySelector( '.is-loadable-placeholder' ) )
	);
};
