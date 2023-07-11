/**
 * External dependencies
 */
const { merchant, shopper, uiUnblocked } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import config from 'config';
import { merchantWCP, shopperWCP } from '../../../utils';
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';

const baseUrl = config.get( 'url' );

describe( 'Fraud protection', () => {
	describe( 'Rules', () => {
		const shippingDetails = {
			...config.get( 'addresses.customer.shipping' ),
			country: 'Brazil',
			city: 'Porto Alegre',
			state: 'Rio Grande do Sul',
			postcode: '90000-000',
		};

		beforeAll( async () => {
			await merchant.login();

			// Add shipping zone method
			await page.waitFor( 1000 );
			await page.goto(
				`${ baseUrl }wp-admin/admin.php?page=wc-settings&tab=shipping&zone_id=0`,
				{
					waitUntil: 'networkidle0',
				}
			);

			const freeShippingExists = await page.$$eval(
				'.wc-shipping-zone-method-type',
				( elements ) =>
					elements.some( ( el ) =>
						el.textContent.includes( 'Free shipping' )
					)
			);

			if ( ! freeShippingExists ) {
				await page.waitFor( 1000 );
				await expect( page ).toClick(
					'button.wc-shipping-zone-add-method',
					{
						text: 'Add shipping method',
					}
				);
				await page.waitForSelector(
					'.wc-shipping-zone-method-selector'
				);
				await expect( page ).toSelect(
					'select[name="add_method_id"]',
					'free_shipping'
				);
				await expect( page ).toClick( 'button#btn-ok' );
				await uiUnblocked();
			}

			// Clear the cart at the end so it's ready for another test
			await shopperWCP.emptyCart();
		} );

		afterEach( async () => {
			// Clear the cart at the end so it's ready for another test
			await shopperWCP.emptyCart();
		} );

		afterAll( async () => {
			await merchantWCP.disableAllFraudProtectionRules();

			// Remove shipping zone method
			await page.waitFor( 1000 );
			await page.goto(
				`${ baseUrl }wp-admin/admin.php?page=wc-settings&tab=shipping&zone_id=0`,
				{
					waitUntil: 'networkidle0',
				}
			);

			const freeShippingExists = await page.$$eval(
				'.wc-shipping-zone-method-type',
				( elements ) =>
					elements.some( ( el ) =>
						el.textContent.includes( 'Free shipping' )
					)
			);

			if ( freeShippingExists ) {
				await expect( page ).toClick(
					'.wc-shipping-zone-method-delete'
				);
				await expect( page ).toClick( 'button#btn-ok' );
				await uiUnblocked();
			}

			await merchant.logout();
		} );

		it( 'Address Mismatch', async () => {
			await merchantWCP.disableAllFraudProtectionRules();
			await merchantWCP.toggleFraudProtectionRule( 'address-mismatch' );

			await setupProductCheckout(
				config.get( 'addresses.customer.billing' ),
				[ [ config.get( 'products.simple.name' ), 1 ] ],
				shippingDetails
			);

			await fillCardDetails( page, config.get( 'cards.basic' ) );
			await expect( page ).toClick( '#place_order' );
			await shopperWCP.validateCheckoutError();

			await merchantWCP.openLatestBlockedOrder();
			await merchantWCP.checkTransactionStatus( 'Payment blocked' );
			await merchantWCP.checkFraudOutcomeEntry(
				'Block if the shipping address differs from the billing address'
			);
		} );

		describe( 'Purchase price threshold', () => {
			it( 'Blocks the checkout if the minimum limit is not reach', async () => {
				await merchantWCP.disableAllFraudProtectionRules();
				await merchantWCP.toggleFraudProtectionRule(
					'purchase-price-threshold',
					{
						'#fraud-protection-purchase-price-minimum': '500',
						'#fraud-protection-purchase-price-maximum': '',
					}
				);
				await setupProductCheckout(
					config.get( 'addresses.customer.billing' )
				);
				await fillCardDetails( page, config.get( 'cards.basic' ) );
				await expect( page ).toClick( '#place_order' );
				await shopperWCP.validateCheckoutError();
				await merchantWCP.openLatestBlockedOrder();
				await merchantWCP.checkTransactionStatus( 'Payment blocked' );
				await merchantWCP.checkFraudOutcomeEntry(
					'Block if the purchase price is not in your defined range'
				);
			} );

			it( 'Blocks the checkout when hitting the maximum limit', async () => {
				await merchantWCP.disableAllFraudProtectionRules();
				await merchantWCP.toggleFraudProtectionRule(
					'purchase-price-threshold',
					{
						'#fraud-protection-purchase-price-minimum': '',
						'#fraud-protection-purchase-price-maximum': '5',
					}
				);

				await setupProductCheckout(
					config.get( 'addresses.customer.billing' ),
					[ [ config.get( 'products.simple.name' ), 5 ] ]
				);
				await fillCardDetails( page, config.get( 'cards.basic' ) );
				await expect( page ).toClick( '#place_order' );
				await shopperWCP.validateCheckoutError();

				await merchantWCP.openLatestBlockedOrder();
				await merchantWCP.checkTransactionStatus( 'Payment blocked' );
				await merchantWCP.checkFraudOutcomeEntry(
					'Block if the purchase price is not in your defined range'
				);
			} );

			it( 'Allows the checkout if the price is in between the limits', async () => {
				await merchantWCP.disableAllFraudProtectionRules();
				await merchantWCP.toggleFraudProtectionRule(
					'purchase-price-threshold',
					{
						'#fraud-protection-purchase-price-minimum': '1',
						'#fraud-protection-purchase-price-maximum': '50',
					}
				);

				await setupProductCheckout(
					config.get( 'addresses.customer.billing' )
				);
				await fillCardDetails( page, config.get( 'cards.basic' ) );
				await shopper.placeOrder();
				await expect( page ).toMatch( 'Order received' );
			} );
		} );

		describe( 'Order items threshold', () => {
			it( 'Blocks the checkout if the minimum limit is not reach', async () => {
				await merchantWCP.disableAllFraudProtectionRules();
				await merchantWCP.toggleFraudProtectionRule(
					'order-items-threshold',
					{
						'#fraud-protection-order-items-minimum': '2',
						'#fraud-protection-order-items-maximum': '',
					}
				);

				await setupProductCheckout(
					config.get( 'addresses.customer.billing' )
				);
				await fillCardDetails( page, config.get( 'cards.basic' ) );
				await expect( page ).toClick( '#place_order' );
				await shopperWCP.validateCheckoutError();

				await merchantWCP.openLatestBlockedOrder();
				await merchantWCP.checkTransactionStatus( 'Payment blocked' );
				await merchantWCP.checkFraudOutcomeEntry(
					'Block if the items count is not in your defined range'
				);
			} );

			it( 'Blocks the checkout when hitting the maximum limit', async () => {
				await merchantWCP.disableAllFraudProtectionRules();
				await merchantWCP.toggleFraudProtectionRule(
					'order-items-threshold',
					{
						'#fraud-protection-order-items-minimum': '',
						'#fraud-protection-order-items-maximum': '3',
					}
				);

				await setupProductCheckout(
					config.get( 'addresses.customer.billing' ),
					[ [ config.get( 'products.simple.name' ), 5 ] ]
				);
				await fillCardDetails( page, config.get( 'cards.basic' ) );
				await expect( page ).toClick( '#place_order' );
				await shopperWCP.validateCheckoutError();

				await merchantWCP.openLatestBlockedOrder();
				await merchantWCP.checkTransactionStatus( 'Payment blocked' );
				await merchantWCP.checkFraudOutcomeEntry(
					'Block if the items count is not in your defined range'
				);
			} );

			it( 'Allows the checkout if the price is in between the limits', async () => {
				await merchantWCP.disableAllFraudProtectionRules();
				await merchantWCP.toggleFraudProtectionRule(
					'order-items-threshold',
					{
						'#fraud-protection-order-items-minimum': '1',
						'#fraud-protection-order-items-maximum': '10',
					}
				);

				await setupProductCheckout(
					config.get( 'addresses.customer.billing' ),
					[ [ config.get( 'products.simple.name' ), 3 ] ]
				);
				await fillCardDetails( page, config.get( 'cards.basic' ) );
				await shopper.placeOrder();
				await expect( page ).toMatch( 'Order received' );
			} );
		} );
	} );
} );
