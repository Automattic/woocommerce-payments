/**
 * Debug test file for experimenting with test failures locally.
 * DO NOT COMMIT - this file is for local testing only.
 */
import { test, expect } from '@playwright/test';

test.describe( 'Debug Tests - Failure Scenarios', () => {
	test( 'should pass - basic assertion', async ( { page } ) => {
		expect( true ).toBe( true );
	} );

	test( 'should pass - page loads', async ( { page } ) => {
		await page.goto( '/' );
		await expect( page ).toHaveTitle( /.+/ );
	} );

	test( 'should FAIL - intentional assertion failure', async ( { page } ) => {
		expect( 'actual' ).toBe( 'expected' );
	} );

	test( 'should FAIL - element not found', async ( { page } ) => {
		await page.goto( '/' );
		await expect(
			page.locator( '#non-existent-element-12345' )
		).toBeVisible( { timeout: 3000 } );
	} );
} );
