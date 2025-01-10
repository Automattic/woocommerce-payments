/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../config/default';
import * as shopper from '../../utils/shopper';
import * as shopperNavigation from '../../utils/shopper-navigation';
import * as devtools from '../../utils/devtools';
import { getMerchant, getShopper } from '../../utils/helpers';

const cardTestingPreventionStates = [
	{ cardTestingPreventionEnabled: false },
	{ cardTestingPreventionEnabled: true },
];

test.describe( 'Shopper > Pay for Order', () => {
	cardTestingPreventionStates.forEach(
		( { cardTestingPreventionEnabled } ) => {
			test( `should be able to pay for a failed order with card testing protection ${ cardTestingPreventionEnabled }`, async ( {
				browser,
			} ) => {
				const { merchantPage } = await getMerchant( browser );
				const { shopperPage } = await getShopper( browser );

				// Enable or disable card testing protection.
				if ( ! cardTestingPreventionEnabled ) {
					await devtools.disableCardTestingProtection( merchantPage );
				} else {
					await devtools.enableCardTestingProtection( merchantPage );
				}

				// Attempt to pay with a declined card.
				await shopper.addCartProduct( shopperPage );
				await shopper.setupCheckout(
					shopperPage,
					config.addresses.customer.billing
				);
				await shopper.fillCardDetails(
					shopperPage,
					config.cards.declined
				);
				await shopper.placeOrder( shopperPage );

				await expect(
					shopperPage.getByText( 'Your card was declined' ).first()
				).toBeVisible();

				// Go to the orders page and pay with a basic card.
				await shopperNavigation.goToOrders( shopperPage );

				const payForOrderButton = shopperPage
					.locator( '.woocommerce-button.button.pay', {
						hasText: 'Pay',
					} )
					.first();
				await payForOrderButton.click();
				await shopper.fillCardDetails(
					shopperPage,
					config.cards.basic
				);

				// Check for the fraud prevenction token presence.
				const token = await shopperPage.evaluate( () => {
					return ( window as any ).wcpayFraudPreventionToken;
				} );

				if ( cardTestingPreventionEnabled ) {
					expect( token ).not.toBeUndefined();
				} else {
					expect( token ).toBeUndefined();
				}

				// Click the pay for order button.
				await shopper.placeOrder( shopperPage );

				await expect(
					shopperPage.getByText( 'Order received' ).first()
				).toBeVisible();

				// Disable card testing protection if necessary.
				if ( cardTestingPreventionEnabled ) {
					await devtools.disableCardTestingProtection( merchantPage );
				}
			} );
		}
	);
} );
