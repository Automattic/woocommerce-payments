/**
 * External dependencies
 */
const { merchant, withRestApi } = require( '@woocommerce/e2e-utils' ),
	{ testAdminOnboardingWizard } = require( '@woocommerce/admin-e2e-tests' );

describe( 'Onboarding > Resetting to defaults', () => {
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

	it( 'can reset to default settings', async () => {
		await withRestApi.resetSettingsGroupToDefault( 'general' );
		await withRestApi.resetSettingsGroupToDefault( 'products' );
		await withRestApi.resetSettingsGroupToDefault( 'tax' );
	} );
} );

describe( 'Onboarding > Start and complete onboarding', () => {
	testAdminOnboardingWizard();
} );
