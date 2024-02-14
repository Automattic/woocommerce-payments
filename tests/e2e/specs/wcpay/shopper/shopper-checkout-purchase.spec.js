/**
 * External dependencies
 */
import config from 'config';

const { shopper, merchant } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */

import {
	fillCardDetails,
	setupProductCheckout,
	confirmCardAuthentication,
} from '../../../utils/payments';

import { shopperWCP, merchantWCP } from '../../../utils';

const cardTestingPreventionStates = [
	{ cardTestingPreventionEnabled: false },
	{ cardTestingPreventionEnabled: true },
];

describe.each( cardTestingPreventionStates )(
	'Successful purchase, non-site builder theme',
	( { cardTestingPreventionEnabled } ) => {
		beforeAll( async () => {
			if ( cardTestingPreventionEnabled ) {
				await merchant.login();
				await merchantWCP.enableCardTestingProtection();
				await merchant.logout();
			}
		} );

		beforeEach( async () => {
			await shopperWCP.emptyCart();
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
		} );

		afterAll( async () => {
			// Clear the cart at the end so it's ready for another test
			await shopperWCP.emptyCart();
			if ( cardTestingPreventionEnabled ) {
				await merchant.login();
				await merchantWCP.disableCardTestingProtection();
				await merchant.logout();
			}
		} );

		it( `using a basic card, carding protection ${ cardTestingPreventionEnabled }`, async () => {
			if ( cardTestingPreventionEnabled ) {
				const token = await page.evaluate( () => {
					return window.wcpayFraudPreventionToken;
				} );
				expect( token ).not.toBeUndefined();
			}
			const card = config.get( 'cards.basic' );
			await fillCardDetails( page, card );
			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );
		} );

		it( `using a 3DS card, carding protection ${ cardTestingPreventionEnabled }`, async () => {
			if ( cardTestingPreventionEnabled ) {
				const token = await page.evaluate( () => {
					return window.wcpayFraudPreventionToken;
				} );
				expect( token ).not.toBeUndefined();
			}
			const card = config.get( 'cards.3ds' );
			await fillCardDetails( page, card );
			await expect( page ).toClick( '#place_order' );
			await confirmCardAuthentication( page );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatch( 'Order received' );
		} );
	}
);
