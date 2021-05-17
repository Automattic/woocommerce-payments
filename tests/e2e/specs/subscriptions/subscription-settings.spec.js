/**
 * External dependencies
 */
import config from 'config';

const { merchant } = require( '@woocommerce/e2e-utils' );

import { RUN_SUBSCRIPTIONS_TESTS } from '../../utils';

const describeif = ( condition ) => ( condition ? describe : describe.skip );

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'WooCommerce > Settings > Subscriptions',
	() => {
		beforeAll( async () => {
			await page.goto( config.get( 'url' ), {
				waitUntil: 'networkidle0',
			} );
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
