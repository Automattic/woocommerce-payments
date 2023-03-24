/**
 * External dependencies
 */
import { describeif, RUN_SUBSCRIPTIONS_TESTS } from '../../../utils';

const { merchant } = require( '@woocommerce/e2e-utils' );

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'WooCommerce > Settings > Subscriptions',
	() => {
		beforeAll( async () => {
			await merchant.login();
		} );
		afterAll( async () => {
			await merchant.logout();
		} );

		it( 'should be able to load WooCommerce Subscriptions Settings tab', async () => {
			await merchant.openSettings( 'subscriptions' );
			await expect( page ).toMatchElement( 'a.nav-tab-active', {
				text: 'Subscriptions',
			} );
		} );
	}
);
