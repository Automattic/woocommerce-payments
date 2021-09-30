/**
 * External dependencies
 */
import config from 'config';

const { merchant, shopper, withRestApi } = require( '@woocommerce/e2e-utils' );

import { RUN_SUBSCRIPTIONS_TESTS, describeif, merchantWCP } from '../../utils';

import { fillCardDetails, setupCheckout } from '../../utils/payments';

const nowLocal = new Date();
const nextPayDate = 'In 7 days';
const productName = `Subscription product ${ nowLocal.getTime() }`;
const customerBilling = config.get( 'addresses.customer.billing' );
const card = config.get( 'cards.basic' );
const baseUrl = config.get( 'url' );

let productId;
let orderId;
let subscriptionId;

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Subscriptions > Purchase subscription product',
	() => {
		// Setup the subscription product.
		// Take note of the the product ID.
		beforeAll( async () => {
			await merchant.login();
			productId = await merchantWCP.createSubscriptionProduct(
				productName,
				'week'
			);
			await merchant.logout();
		} );

		afterAll( async () => {
			// Delete the user created with the subscription
			await withRestApi.deleteCustomerByEmail( customerBilling.email );
		} );

		it( 'should be able to purchase the subscription product', async () => {
			// As a Shopper purchase the product using a test card.
			await shopper.goToProduct( productId );
			await shopper.addToCart();
			await setupCheckout( customerBilling );
			await fillCardDetails( page, card );
			await shopper.placeOrder();

			// Take note of the the order ID
			const orderIdField = await page.$(
				'.woocommerce-order-overview__order.order > strong'
			);
			orderId = await orderIdField.evaluate( ( el ) => el.innerText );
			subscriptionId = parseInt( orderId, 10 ) + 1;

			// Open to the order.
			const orderUrl = baseUrl + `my-account/view-order/${ orderId }/`;
			await page.goto( orderUrl, {
				waitUntil: 'networkidle0',
			} );

			// Ensure the new subscription is listed there
			await expect( page ).toMatchElement( 'td.subscription-id', {
				text: `#${ subscriptionId }`,
			} );
			await expect( page ).toMatchElement( 'td.subscription-status', {
				text: 'Active',
			} );
			await expect( page ).toMatchElement(
				'td.subscription-next-payment',
				{
					text: `${ nextPayDate }`,
				}
			);
			await expect( page ).toMatchElement( 'td.subscription-total', {
				text: '$9.99',
			} );

			// Ensure 'Subscription' link and 'View' button are opening.
			const subscriptionLink = await page.$( 'td.subscription-id a' );
			const actualSubHref = await subscriptionLink.evaluate( ( el ) =>
				el.getAttribute( 'href' )
			);
			expect( actualSubHref ).toContain(
				`/view-subscription/${ subscriptionId }`
			);

			const viewButton = await page.$( 'td.subscription-actions a' );
			const viewButtonHref = await viewButton.evaluate( ( el ) =>
				el.getAttribute( 'href' )
			);
			expect( viewButtonHref ).toContain(
				`/view-subscription/${ subscriptionId }`
			);

			// Navigate to the Subscription details page.
			await expect( page ).toClick( 'td.subscription-actions a', {
				text: 'View',
			} );
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );
			await expect( page ).toMatchElement( 'h1.entry-title', {
				text: `Subscription #${ subscriptionId }`,
			} );

			// Verify that all details in the Subscription Details table are correct.
			const subDetailsTable = await page.$(
				'table.subscription_details'
			);
			await expect( subDetailsTable ).toMatch( 'Active' );
			await expect( subDetailsTable ).toMatch( nextPayDate );

			// Verify that all details in the 'Subscription totals' table are correct.
			const subTotalsTable = await page.$( 'table.order_details' );
			await expect( subTotalsTable ).toMatch( productName );
			await expect( subTotalsTable ).toMatch( '$9.99 / week' );

			// Verify that all details in 'Related orders' table are correct.
			await expect( page ).toMatchElement( 'td.order-number', {
				text: `#${ orderId }`,
			} );
			await expect( page ).toMatchElement( 'td.order-total', {
				text: '$9.99',
			} );
		} );
	}
);
