/* global page */
const TIMEOUT = 10000;
const URL = 'http://localhost:8082/wp-admin';

describe( 'First e2e test', () => {
	beforeAll( async () => {
		await page.goto( URL, { waitUntil: 'domcontentloaded' } );
	} );
	test( 'page title', async () => {
		const title = await page.title();
		expect( title ).toBe( 'Dashboard' );
	}, TIMEOUT );
} );
