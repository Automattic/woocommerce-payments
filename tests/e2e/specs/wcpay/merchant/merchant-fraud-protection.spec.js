/**
 * External dependencies
 */
const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import config from 'config';
import { merchantWCP, shopperWCP } from '../../../utils';
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';

describe( 'Fraud protection', () => {
	describe( 'Rules', () => {
		beforeEach( async () => {
			await merchant.login();
			await shopperWCP.emptyCart();
		} );

		afterEach( async () => {
			await shopperWCP.emptyCart();
		} );

		afterAll( async () => {
			await merchantWCP.openWCPSettings();
			await merchantWCP.skipFraudProtectionTour();
			await expect( page ).toClick( '#fraud-protection__basic-level' );
			await merchantWCP.wcpSettingsSaveChanges();

			await merchant.logout();
		} );

		describe( 'Purchase price threshold', () => {
			it( 'Blocks the checkout when hitting the maximum limit', async () => {
				await merchantWCP.disableAllFraudProtectionRules();
				await merchantWCP.toggleFraudProtectionRule(
					'purchase-price-threshold',
					{
						'#fraud-protection-purchase-price-minimum': '',
						'#fraud-protection-purchase-price-maximum': '500',
					}
				);
				await merchantWCP.saveAdvancedFraudProtectionSettings();

				await setupProductCheckout(
					config.get( 'addresses.customer.billing' ),
					[ [ config.get( 'products.simple.name' ), 105 ] ]
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
				await merchantWCP.saveAdvancedFraudProtectionSettings();

				await setupProductCheckout(
					config.get( 'addresses.customer.billing' )
				);
				await fillCardDetails( page, config.get( 'cards.basic' ) );
				await shopper.placeOrder();
				await expect( page ).toMatch( 'Order received' );
			} );
		} );

		describe( 'Order items threshold', () => {
			it( 'Blocks the checkout when hitting the maximum limit', async () => {
				await merchantWCP.disableAllFraudProtectionRules();
				await merchantWCP.toggleFraudProtectionRule(
					'order-items-threshold',
					{
						'#fraud-protection-order-items-minimum': '',
						'#fraud-protection-order-items-maximum': '100',
					}
				);
				await merchantWCP.saveAdvancedFraudProtectionSettings();

				await setupProductCheckout(
					config.get( 'addresses.customer.billing' ),
					[ [ config.get( 'products.simple.name' ), 105 ] ]
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
						'#fraud-protection-order-items-maximum': '100',
					}
				);
				await merchantWCP.saveAdvancedFraudProtectionSettings();

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
