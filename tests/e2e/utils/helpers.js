/**
 * External dependencies
 */
import { pressKeyWithModifier } from '@wordpress/e2e-test-utils';

/**
 * Perform a "select all" and then fill a input.
 *
 * @param {string} selector Css selector
 * @param {string} value Input value
 */
export const clearAndFillInput = async ( selector, value ) => {
	await page.focus( selector );
	await pressKeyWithModifier( 'primary', 'a' );
	await page.type( selector, value );
};

/**
 * Click a tab (on post type edit screen).
 *
 * @param {string} tabName Tab label
 */
export const clickTab = async ( tabName ) => {
	await expect( page ).toClick( '.wc-tabs > li > a', { text: tabName } );
};

/**
 * Save changes on a WooCommerce settings page.
 */
export const settingsPageSaveChanges = async () => {
	await page.focus( 'button.woocommerce-save-button' );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'networkidle0' } ),
		page.click( 'button.woocommerce-save-button' ),
	] );
};

/**
 * Save changes on Permalink settings page.
 */
export const permalinkSettingsPageSaveChanges = async () => {
	await page.focus( '.wp-core-ui .button-primary' );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'networkidle0' } ),
		page.click( '.wp-core-ui .button-primary' ),
	] );
};

/**
 * Set checkbox.
 *
 * @param {string} selector CSS selector
 */
export const setCheckbox = async ( selector ) => {
	await page.focus( selector );
	const checkbox = await page.$( selector );
	const checkboxStatus = await (
		await checkbox.getProperty( 'checked' )
	 ).jsonValue();
	if ( true !== checkboxStatus ) {
		await page.click( selector );
	}
};

/**
 * Unset checkbox.
 *
 * @param {string} selector CSS selector
 */
export const unsetCheckbox = async ( selector ) => {
	await page.focus( selector );
	const checkbox = await page.$( selector );
	const checkboxStatus = await (
		await checkbox.getProperty( 'checked' )
	 ).jsonValue();
	if ( true === checkboxStatus ) {
		await page.click( selector );
	}
};

/**
 * Wait for UI blocking to end.
 */
export const uiUnblocked = async () => {
	await page.waitForFunction(
		() => ! Boolean( document.querySelector( '.blockUI' ) )
	);
};

/**
 * Wait for UI placeholders to finish and UI content is loaded.
 */
export const uiLoaded = async () => {
	await page.waitForFunction(
		() => ! Boolean( document.querySelector( '.is-loadable-placeholder' ) )
	);
};

/**
 * Publish, verify that item was published. Trash, verify that item was trashed.
 *
 * @param {string} button (Publish)
 * @param {string} publishNotice Publish notice
 * @param {string} publishVerification Publish verification
 * @param {string} trashVerification Trash verification
 */
export const verifyPublishAndTrash = async (
	button,
	publishNotice,
	publishVerification,
	trashVerification
) => {
	// Wait for auto save
	await page.waitFor( 2000 );

	// Publish
	await expect( page ).toClick( button );
	await page.waitForSelector( publishNotice );

	// Verify
	await expect( page ).toMatchElement( publishNotice, {
		text: publishVerification,
	} );
	if ( '.order_actions li .save_order' === button ) {
		await expect( page ).toMatchElement(
			'#select2-order_status-container',
			{ text: 'Processing' }
		);
		await expect( page ).toMatchElement(
			'#woocommerce-order-notes .note_content',
			{
				text:
					'Order status changed from Pending payment to Processing.',
			}
		);
	}

	// Trash
	await expect( page ).toClick( 'a', { text: 'Move to Trash' } );
	await page.waitForSelector( '#message' );

	// Verify
	await expect( page ).toMatchElement( publishNotice, {
		text: trashVerification,
	} );
};

/**
 * Verify that checkbox is set.
 *
 * @param {string} selector Selector of the checkbox that needs to be verified.
 */
export const verifyCheckboxIsSet = async ( selector ) => {
	await page.focus( selector );
	const checkbox = await page.$( selector );
	const checkboxStatus = await (
		await checkbox.getProperty( 'checked' )
	 ).jsonValue();
	await expect( checkboxStatus ).toBe( true );
};

/**
 * Verify that checkbox is unset.
 *
 * @param {string} selector Selector of the checkbox that needs to be verified.
 */
export const verifyCheckboxIsUnset = async ( selector ) => {
	await page.focus( selector );
	const checkbox = await page.$( selector );
	const checkboxStatus = await (
		await checkbox.getProperty( 'checked' )
	 ).jsonValue();
	await expect( checkboxStatus ).not.toBe( true );
};

/**
 * Verify the value of input field once it was saved (can be used for radio buttons verification as well).
 *
 * @param {string} selector Selector of the input field that needs to be verified.
 * @param {string} value Value of the input field that needs to be verified.
 */
export const verifyValueOfInputField = async ( selector, value ) => {
	await page.focus( selector );
	const field = await page.$( selector );
	const fieldValue = await ( await field.getProperty( 'value' ) ).jsonValue();
	await expect( fieldValue ).toBe( value );
};
