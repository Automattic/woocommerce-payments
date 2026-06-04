/**
 * Regression test for the dev-tools settings page resilience in
 * `utils/devtools.ts`. The real page is intermittently truncated by a PHP fatal
 * that drops its "Save Changes" button; here we mock that render via route
 * interception so the recover-and-fail-fast behaviour is exercised deterministically
 * (the real flake is intermittent and environment-dependent).
 *
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { goToDevToolsSettings } from '../../../utils/devtools';

const devToolsUrl = /\/wp-admin\/admin\.php\?page=wcpaydev/;

// The button is the last thing rendered, so a render with it = a full page, and
// one without it = a page truncated mid-render.
const renderHtml = ( withSaveButton: boolean ) =>
	`<!doctype html><title>WCPay Dev Tools</title>${
		withSaveButton ? '<input type="submit" value="Save Changes">' : ''
	}`;

test.describe( 'Dev Tools settings page render resilience', () => {
	test( 'recovers when a truncated render is followed by a full one', async ( {
		page,
	} ) => {
		let hits = 0;
		await page.route( devToolsUrl, ( route ) => {
			hits++;
			return route.fulfill( {
				contentType: 'text/html',
				body: renderHtml( hits > 1 ), // first load truncated, then full
			} );
		} );

		await expect(
			goToDevToolsSettings( page, { renderTimeoutMs: 1000 } )
		).resolves.toBeUndefined();
		expect( hits ).toBe( 2 );
	} );

	test( 'fails fast with a clear error when the page stays truncated', async ( {
		page,
	} ) => {
		await page.route( devToolsUrl, ( route ) =>
			route.fulfill( {
				contentType: 'text/html',
				body: renderHtml( false ),
			} )
		);

		await expect(
			goToDevToolsSettings( page, {
				renderTimeoutMs: 500,
				maxLoadAttempts: 2,
			} )
		).rejects.toThrow( /did not render its "Save Changes" button/ );
	} );
} );
