/**
 * External dependencies
 */
const {
	merchant,
	addShippingZoneAndMethod,
	withRestApi,
} = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import config from 'config';
import { merchantWCP, shopperWCP } from '../../../utils';
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';

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
		} );

		afterEach( async () => {
			// Clear the cart at the end so it's ready for another test
			await shopperWCP.emptyCart();
		} );

		it( 'Address Mismatch', async () => {
			await addShippingZoneAndMethod(
				'Free Shipping',
				'country:BR',
				' ',
				'free_shipping'
			);

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

			await withRestApi.deleteAllShippingZones( false );

			await merchantWCP.openLatestBlockedOrder();
			await merchantWCP.checkTransactionStatus( 'Payment blocked' );
			await merchantWCP.checkFraudOutcomeEntry(
				'Block if the shipping address differs from the billing address'
			);
		} );

		it( 'Purchase price threshold', async () => {
			await merchantWCP.disableAllFraudProtectionRules();
			await merchantWCP.toggleFraudProtectionRule(
				'purchase-price-threshold',
				{ '#fraud-protection-purchase-price-maximum': '5' }
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
	} );
} );
