/**
 * External dependencies
 */
import { test as setup, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { getMerchant } from '../utils/helpers';

setup( 'disable the coming soon mode', async ( { browser } ) => {
	const { merchantPage } = await getMerchant( browser );

	// Check if the store is in coming soon mode.
	await merchantPage.goto( '/wp-admin/' );
	await merchantPage.waitForLoadState( 'domcontentloaded' );

	const comingSoonBadge = merchantPage.getByText( 'Store coming soon' );

	if ( ! ( await comingSoonBadge.isVisible() ) ) {
		return;
	}

	// Disable the coming soon mode.
	await comingSoonBadge.click();
	await expect(
		merchantPage.getByText( 'Manage how your site appears to visitors.' )
	).toBeVisible();
	await merchantPage.waitForLoadState( 'domcontentloaded' );

	await merchantPage.locator( 'label' ).filter( { hasText: 'Live' } ).click();
	await merchantPage
		.locator( '.woocommerce-save-button', { hasText: 'Save changes' } )
		.click();

	await expect(
		merchantPage.getByText( /Your settings have been saved/ )
	).toBeVisible();
} );
