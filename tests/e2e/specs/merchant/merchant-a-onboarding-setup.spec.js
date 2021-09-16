/**
 * External dependencies
 */
const {
	merchant,
	completeOnboardingWizard,
	withRestApi,
} = require( '@woocommerce/e2e-utils' );

describe( 'Onboarding > WooCommerce Setup Wizard & Task List', () => {
	beforeAll( async () => {
		await merchant.login();
		await withRestApi.resetOnboarding();
		await withRestApi.deleteAllShippingZones();
		await withRestApi.resetSettingsGroupToDefault( 'general' );
		await withRestApi.resetSettingsGroupToDefault( 'products' );
		await withRestApi.resetSettingsGroupToDefault( 'tax' );
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'can complete onboarding when visiting the first time', async () => {
		await completeOnboardingWizard();
	} );
} );
