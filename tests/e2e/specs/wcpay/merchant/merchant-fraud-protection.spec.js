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
		beforeAll( async () => {
			await merchant.login();
			await shopperWCP.emptyCart();
			await merchant.logout();
		} );

		afterAll( async () => {
			await merchant.login();
			await merchantWCP.openWCPSettings();
			await merchantWCP.skipFraudProtectionTour();
			await expect( page ).toClick( '#fraud-protection__basic-level' );
			await merchantWCP.wcpSettingsSaveChanges();
			await merchant.logout();
		} );

		describe( 'Purchase price threshold', () => {
			it( 'Blocks the checkout when hitting the maximum limit', async () => {
				await merchant.login();
				await merchantWCP.disableAllFraudProtectionRules();
				await merchantWCP.toggleFraudProtectionRule(
					'purchase-price-threshold',
					{
						'#fraud-protection-purchase-price-minimum': '',
						'#fraud-protection-purchase-price-maximum': '50',
					}
				);
				await merchantWCP.saveAdvancedFraudProtectionSettings();
				await merchant.logout();

				await shopper.login();
				await shopperWCP.emptyCart();
				await setupProductCheckout(
					config.get( 'addresses.customer.billing' ),
					[ [ config.get( 'products.simple.name' ), 10 ] ]
				);
				await fillCardDetails( page, config.get( 'cards.basic' ) );
				await expect( page ).toClick( '#place_order' );
				await shopperWCP.validateCheckoutError();
				await shopperWCP.emptyCart();
				await shopper.logout();

				await merchant.login();
				await merchantWCP.openLatestBlockedOrder();
				await merchantWCP.checkTransactionStatus( 'Payment blocked' );
				await merchantWCP.checkFraudOutcomeEntry(
					'Block if the purchase price is not in your defined range'
				);
				await merchant.logout();
			} );
		} );

		describe( 'Order items threshold', () => {
			it( 'Blocks the checkout when hitting the maximum limit', async () => {
				await merchant.login();
				await merchantWCP.disableAllFraudProtectionRules();
				await merchantWCP.toggleFraudProtectionRule(
					'order-items-threshold',
					{
						'#fraud-protection-order-items-minimum': '',
						'#fraud-protection-order-items-maximum': '10',
					}
				);
				await merchantWCP.saveAdvancedFraudProtectionSettings();
				await merchant.logout();

				await shopper.login();
				await shopperWCP.emptyCart();
				await setupProductCheckout(
					config.get( 'addresses.customer.billing' ),
					[ [ config.get( 'products.simple.name' ), 11 ] ]
				);
				await fillCardDetails( page, config.get( 'cards.basic' ) );
				await expect( page ).toClick( '#place_order' );
				await shopperWCP.validateCheckoutError();
				await shopperWCP.emptyCart();
				await shopper.logout();

				await merchant.login();
				await merchantWCP.openLatestBlockedOrder();
				await merchantWCP.checkTransactionStatus( 'Payment blocked' );
				await merchantWCP.checkFraudOutcomeEntry(
					'Block if the items count is not in your defined range'
				);
				await merchant.logout();
			} );
		} );
	} );
} );
