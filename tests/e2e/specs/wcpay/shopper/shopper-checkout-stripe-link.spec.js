/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { merchantWCP, shopperWCP } from '../../../utils/flows';
import { randomizeEmail, getInputTextValue } from '../../../utils';
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';
const { shopper, merchant, uiUnblocked } = require( '@woocommerce/e2e-utils' );

const linkPaymentsCheckbox = '#inspector-checkbox-control-10';

describe( 'Link with enabled UPE', () => {
	describe( 'UPE enabled', () => {
		const billingAddress = {
			...config.get( 'addresses.customer.billing' ),
			email: randomizeEmail(
				config.get( 'addresses.customer.billing.email' )
			),
			phone: '14587777777',
		};

		beforeAll( async () => {
			await merchant.login();
			await merchantWCP.activateUpe();
			await merchantWCP.enablePaymentMethod( linkPaymentsCheckbox );
			await merchant.logout();
			await shopper.login();
		} );

		afterAll( async () => {
			await merchant.login();
			await merchantWCP.disablePaymentMethod(
				linkPaymentsCheckbox,
				false
			);
			await merchantWCP.deactivateUpe();
			await merchant.logout();
		} );

		it( 'should save account details to Link', async () => {
			await setupProductCheckout( billingAddress );
			await shopperWCP.selectNewPaymentMethod();
			const card = config.get( 'cards.basic' );
			await fillCardDetails( page, card );

			await shopperWCP.fillStripeLinkDetails( billingAddress );
			const button = await page.$( '.wcpay-stripelink-modal-trigger' );
			expect( button ).toBeTruthy();

			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );
			await shopperWCP.logout();
		} );

		it( 'should use the saved Link account to fill in the details', async () => {
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( 'Beanie' );
			await shopper.goToCheckout();
			await uiUnblocked();

			await expect( page ).toFill(
				'#billing_phone',
				billingAddress.phone
			);
			await expect( page ).toFill(
				'#billing_email',
				billingAddress.email
			);
			await shopperWCP.autofillExistingStripeLinkAccount();

			expect( billingAddress.firstname ).toContain(
				await getInputTextValue( '#billing_first_name' )
			);
			expect( await getInputTextValue( '#billing_last_name' ) ).toContain(
				billingAddress.lastname
			);
			expect( await getInputTextValue( '#billing_city' ) ).toEqual(
				billingAddress.city
			);
			expect( await getInputTextValue( '#billing_state' ) ).toEqual(
				billingAddress.state
			);
			expect( await getInputTextValue( '#billing_postcode' ) ).toEqual(
				billingAddress.postcode
			);

			const billingCountrySelector = await page.$(
				'#select2-billing_country-container'
			);
			const billingCountryProperty = await billingCountrySelector.getProperty(
				'title'
			);
			const billingCountry = await billingCountryProperty.jsonValue();
			expect( billingCountry ).toEqual( billingAddress.country );

			await uiUnblocked();

			await expect( page ).toClick( '#place_order' );

			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatch( 'Order received' );
		} );
	} );

	describe( 'Split UPE enabled', () => {
		const billingAddress = {
			...config.get( 'addresses.customer.billing' ),
			email: randomizeEmail(
				config.get( 'addresses.customer.billing.email' )
			),
			phone: '14587777777',
		};

		beforeAll( async () => {
			await merchant.login();
			await merchantWCP.activateSplitUpe();
			await merchantWCP.enablePaymentMethod( linkPaymentsCheckbox );
			await merchant.logout();
			await shopper.login();
		} );

		afterAll( async () => {
			await merchant.login();
			await merchantWCP.disablePaymentMethod(
				linkPaymentsCheckbox,
				false
			);
			await merchantWCP.deactivateSplitUpe();
			await merchant.logout();
		} );

		it( 'should save account details to Link', async () => {
			await setupProductCheckout( billingAddress );
			await shopperWCP.selectNewPaymentMethod();
			const card = config.get( 'cards.basic' );
			await fillCardDetails( page, card );

			await shopperWCP.fillStripeLinkDetails( billingAddress );
			const button = await page.$( '.wcpay-stripelink-modal-trigger' );
			expect( button ).toBeTruthy();

			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );
			await shopperWCP.logout();
		} );

		it( 'should use the saved Link account to fill in the details', async () => {
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( 'Beanie' );
			await shopper.goToCheckout();
			await uiUnblocked();

			await expect( page ).toFill(
				'#billing_phone',
				billingAddress.phone
			);
			await expect( page ).toFill(
				'#billing_email',
				billingAddress.email
			);
			await shopperWCP.autofillExistingStripeLinkAccount();

			expect( billingAddress.firstname ).toContain(
				await getInputTextValue( '#billing_first_name' )
			);
			expect( await getInputTextValue( '#billing_last_name' ) ).toContain(
				billingAddress.lastname
			);
			expect( await getInputTextValue( '#billing_city' ) ).toEqual(
				billingAddress.city
			);
			expect( await getInputTextValue( '#billing_state' ) ).toEqual(
				billingAddress.state
			);
			expect( await getInputTextValue( '#billing_postcode' ) ).toEqual(
				billingAddress.postcode
			);

			const billingCountrySelector = await page.$(
				'#select2-billing_country-container'
			);
			const billingCountryProperty = await billingCountrySelector.getProperty(
				'title'
			);
			const billingCountry = await billingCountryProperty.jsonValue();
			expect( billingCountry ).toEqual( billingAddress.country );

			await uiUnblocked();

			await expect( page ).toClick( '#place_order' );

			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatch( 'Order received' );
		} );
	} );
} );
