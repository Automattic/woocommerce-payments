/**
 * External dependencies
 */
const {
	testAdminHomescreenTasklist,
} = require( '@woocommerce/admin-e2e-tests' );

// Unskip this when retries in individual tests are enabled to avoid flakiness.
describe.skip( 'Onboarding > WooCommerce Setup Wizard & Task List', () => {
	testAdminHomescreenTasklist();
} );
