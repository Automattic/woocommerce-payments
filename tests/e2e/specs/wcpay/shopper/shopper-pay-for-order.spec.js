const { uiUnblocked, shopper, merchant } = require( '@woocommerce/e2e-utils' );
const {
	setupProductCheckout,
	fillCardDetails,
	fillCardDetailsPayForOrder,
} = require( '../../../utils/payments' );
const config = require( 'config' );
const { shopperWCP, merchantWCP } = require( '../../../utils' );

const cardTestingPreventionStates = [
	{ cardTestingPreventionEnabled: false },
	{ cardTestingPreventionEnabled: true },
];

describe.each( cardTestingPreventionStates )(
	'Shopper > Pay for Order',
	( { cardTestingPreventionEnabled } ) => {
		beforeAll( async () => {
			if ( cardTestingPreventionEnabled ) {
				await merchant.login();
				await merchantWCP.enableCardTestingProtection();
				await merchant.logout();
			}
			await shopper.login();
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
		} );

		afterAll( async () => {
			await shopper.logout();
			if ( cardTestingPreventionEnabled ) {
				await merchant.login();
				await merchantWCP.disableCardTestingProtection();
				await merchant.logout();
			}
		} );

		it( `should be able to pay for a failed order, carding protection ${ cardTestingPreventionEnabled }`, async () => {
			// try to pay with a declined card
			const declinedCard = config.get( 'cards.declined' );
			await shopperWCP.selectNewPaymentMethod();
			await fillCardDetails( page, declinedCard );
			await expect( page ).toClick( '#place_order' );
			await uiUnblocked();
			await shopperWCP.waitForErrorBanner(
				'Error: Your card was declined.',
				'div.wc-block-components-notice-banner',
				'div.woocommerce-NoticeGroup > ul.woocommerce-error > li'
			);

			// after the card has been declined, go to the order page and pay with a basic card
			await shopperWCP.goToOrders();

			const payButtons = await page.$$(
				'.woocommerce-button.button.pay'
			);
			const payButton = payButtons.find(
				async ( button ) =>
					( await page.evaluate(
						( elem ) => elem.innerText,
						button
					) ) === 'Pay'
			);
			await payButton.click();
			const card = config.get( 'cards.basic' );
			await fillCardDetailsPayForOrder( page, card );

			// Check the token presence when card testing prevention is enabled.
			if ( cardTestingPreventionEnabled ) {
				const token = await page.evaluate( () => {
					return window.wcpayFraudPreventionToken;
				} );
				expect( token ).not.toBeUndefined();
			}

			await expect( page ).toClick( 'button', { text: 'Pay for order' } );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatch( 'Order received' );
		} );
	}
);
