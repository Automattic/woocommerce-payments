/**
 * External dependencies
 */
import config from 'config';
const { merchant } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { setupProductCheckout } from '../../utils/payments';
import { shopperWCP, merchantWCP } from '../../utils';
import {
	recreatePerformanceFile,
	logPerformanceResult,
	measureCheckoutMetrics,
	averageMetrics,
} from '../../utils/performance';

describe( 'Checkout page performance', () => {
	beforeAll( async () => {
		// Start a new file for every run.
		recreatePerformanceFile();
	} );

	describe( 'Stripe element', () => {
		beforeEach( async () => {
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
		} );

		afterEach( async () => {
			// Clear the cart at the end so it's ready for another test
			await shopperWCP.emptyCart();
		} );

		it( 'measures averaged page load metrics', async () => {
			const results = await measureCheckoutMetrics(
				'#wcpay-card-element iframe'
			);
			logPerformanceResult(
				'Stripe element: Average',
				averageMetrics( results )
			);
		} );
	} );

	describe( 'UPE', () => {
		beforeEach( async () => {
			// Activate UPE
			await merchant.login();
			await merchantWCP.activateUpe();
			await merchant.logout();

			// Setup cart
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
		} );

		afterEach( async () => {
			// Clear the cart at the end so it's ready for another test
			await shopperWCP.emptyCart();

			// Deactivate UPE
			await merchant.login();
			await merchantWCP.deactivateUpe();
			await merchant.logout();
		} );

		it( 'measures averaged page load metrics', async () => {
			const results = await measureCheckoutMetrics(
				'#wcpay-upe-element iframe'
			);
			logPerformanceResult(
				'Stripe UPE: Average',
				averageMetrics( results )
			);
		} );
	} );

	describe( 'WooPay withou UPE', () => {
		beforeEach( async () => {
			// Activate UPE
			await merchant.login();
			await merchantWCP.activateWooPay();
			await merchant.logout();

			// Setup cart
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
		} );

		afterEach( async () => {
			// Clear the cart at the end so it's ready for another test
			await shopperWCP.emptyCart();

			// Deactivate UPE
			await merchant.login();
			await merchantWCP.deactivateWooPay();
			await merchant.logout();
		} );

		it( 'measures averaged page load metrics', async () => {
			const results = await measureCheckoutMetrics(
				'#wcpay-card-element iframe'
			);
			logPerformanceResult(
				'WooPay: Average',
				averageMetrics( results )
			);
		} );
	} );
} );
