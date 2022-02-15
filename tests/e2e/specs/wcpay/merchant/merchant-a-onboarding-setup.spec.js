/**
 * External dependencies
 */
const {
		merchant,
		withRestApi,
		IS_RETEST_MODE,
	} = require( '@woocommerce/e2e-utils' ),
	{ testAdminOnboardingWizard } = require( '@woocommerce/admin-e2e-tests' );

describe.skip( 'Onboarding > Resetting to defaults', () => {
	beforeAll( async () => {
		await merchant.login();
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'can reset onboarding to default settings', async () => {
		await withRestApi.resetOnboarding();
	} );

	it( 'can reset shipping zones to default settings', async () => {
		await withRestApi.deleteAllShippingZones();
	} );

	it( 'can reset shipping classes', async () => {
		await withRestApi.deleteAllShippingClasses();
	} );

	it( 'can reset to default settings', async () => {
		await withRestApi.resetSettingsGroupToDefault( 'general' );
		await withRestApi.resetSettingsGroupToDefault( 'products' );
		await withRestApi.resetSettingsGroupToDefault( 'tax' );
	} );
} );

describe.skip( 'Onboarding > Start and complete onboarding', () => {
	// Reset onboarding profile when re-running tests on a site
	if ( IS_RETEST_MODE ) {
		withRestApi.resetOnboarding();
	}

	testAdminOnboardingWizard();
} );
