/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { merchantWCP, shopperWCP } from '../../../utils/flows';
import {
	setupProductCheckout,
	selectOnCheckout,
	completeRedirectedPayment,
} from '../../../utils/payments';
const { shopper, merchant } = require( '@woocommerce/e2e-utils' );

const UPE_METHOD_CHECKBOXES = [
	"//label[contains(text(), 'Bancontact')]/preceding-sibling::span/input[@type='checkbox']",
];
const card = config.get( 'cards.basic' );
const card2 = config.get( 'cards.basic2' );
const MIN_WAIT_TIME_BETWEEN_PAYMENT_METHODS = 20000;

const cardTestingPreventionStates = [
	{ cardTestingPreventionEnabled: false },
	{ cardTestingPreventionEnabled: true },
];

describe.each( cardTestingPreventionStates )(
	'Enabled UPE with deferred intent creation',
	( { cardTestingPreventionEnabled } ) => {
		let wasMulticurrencyEnabled;

		beforeAll( async () => {
			await merchant.login();
			wasMulticurrencyEnabled = await merchantWCP.activateMulticurrency();
			await merchantWCP.addCurrency( 'EUR' );
			await merchantWCP.enablePaymentMethod( UPE_METHOD_CHECKBOXES );
			if ( cardTestingPreventionEnabled ) {
				await merchantWCP.enableCardTestingProtection();
			}
			await merchant.logout();
			await shopper.login();
			await shopperWCP.changeAccountCurrencyTo(
				config.get( 'addresses.upe-customer.billing.de' ),
				'EUR'
			);
			await shopperWCP.emptyCart();
		} );

		afterAll( async () => {
			await shopperWCP.emptyCart();
			await shopperWCP.logout();
			await merchant.login();
			await merchantWCP.disablePaymentMethod( UPE_METHOD_CHECKBOXES );
			if ( cardTestingPreventionEnabled ) {
				await merchantWCP.disableCardTestingProtection();
			}
			if ( ! wasMulticurrencyEnabled ) {
				await merchantWCP.deactivateMulticurrency();
			}
			await merchant.logout();
		} );

		describe( 'Enabled UPE with deferred intent creation', () => {
			it( `should successfully place order with Bancontact, carding prevention: ${ cardTestingPreventionEnabled }`, async () => {
				await setupProductCheckout(
					config.get( 'addresses.upe-customer.billing.be' )
				);
				await page.waitForTimeout( 1000 );
				if ( cardTestingPreventionEnabled ) {
					const token = await page.evaluate( () => {
						return window.wcpayFraudPreventionToken;
					} );
					expect( token ).not.toBeUndefined();
				}
				await selectOnCheckout( 'bancontact', page );
				await shopper.placeOrder();
				await completeRedirectedPayment( page, 'success' );
				await page.waitForNavigation( {
					waitUntil: 'networkidle0',
				} );
				await expect( page ).toMatchTextContent( 'Order received' );
			} );
		} );

		// No need to run these tests for card testing prevention checks.
		if ( ! cardTestingPreventionEnabled ) {
			describe( 'My Account', () => {
				let timeAdded;

				it( 'should add the card as a new payment method', async () => {
					await shopperWCP.goToPaymentMethods();
					await shopperWCP.addNewPaymentMethod( 'basic', card );

					// Take note of the time when we added this card
					timeAdded = Date.now();

					// Verify that the card was added
					await expect( page ).not.toMatchTextContent(
						'You cannot add a new payment method so soon after the previous one. Please wait for 20 seconds.'
					);
					await expect( page ).toMatchTextContent(
						'Payment method successfully added'
					);
					await expect( page ).toMatchTextContent(
						`${ card.expires.month }/${ card.expires.year }`
					);
					await waitTwentySecondsSinceLastCardAdded();
				} );

				it( 'should be able to set payment method as default', async () => {
					await shopperWCP.goToPaymentMethods();
					await shopperWCP.addNewPaymentMethod( 'basic2', card2 );
					// Take note of the time when we added this card
					timeAdded = Date.now();

					// Verify that the card was added
					await expect( page ).not.toMatchTextContent(
						'You cannot add a new payment method so soon after the previous one. Please wait for 20 seconds.'
					);
					await expect( page ).toMatchTextContent(
						'Payment method successfully added'
					);
					await expect( page ).toMatchTextContent(
						`${ card2.expires.month }/${ card2.expires.year }`
					);
					await shopperWCP.setDefaultPaymentMethod( card2.label );
					// Verify that the card was set as default
					await expect( page ).toMatchTextContent(
						'This payment method was successfully set as your default.'
					);
				} );

				it( 'should be able to delete cards', async () => {
					await shopperWCP.deleteSavedPaymentMethod( card.label );
					await expect( page ).toMatchTextContent(
						'Payment method deleted.'
					);

					await shopperWCP.deleteSavedPaymentMethod( card2.label );
					await expect( page ).toMatchTextContent(
						'Payment method deleted.'
					);
				} );

				afterAll( async () => {
					await waitTwentySecondsSinceLastCardAdded();
				} );

				async function waitTwentySecondsSinceLastCardAdded() {
					// Make sure that at least 20s had already elapsed since the last card was added.
					// Otherwise, you will get the error message,
					// "You cannot add a new payment method so soon after the previous one."
					const timeTestFinished = Date.now();
					const elapsedWaitTime = timeTestFinished - timeAdded;
					const remainingWaitTime =
						MIN_WAIT_TIME_BETWEEN_PAYMENT_METHODS > elapsedWaitTime
							? MIN_WAIT_TIME_BETWEEN_PAYMENT_METHODS -
							  elapsedWaitTime
							: 0;

					await new Promise( ( r ) =>
						setTimeout( r, remainingWaitTime )
					);
				}
			} );
		}
	}
);
