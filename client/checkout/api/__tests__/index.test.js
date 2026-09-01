/**
 * Internal dependencies
 */
import WCPayAPI from '..';
import request from 'wcpay/checkout/utils/request';
import { getConfig } from 'wcpay/utils/checkout';

jest.mock( 'wcpay/checkout/utils/request', () =>
	jest.fn( () => Promise.resolve( {} ).finally( () => {} ) )
);
jest.mock( 'wcpay/utils/express-checkout', () => ( {
	buildAjaxURL: jest.fn(),
	getExpressCheckoutConfig: jest.fn(),
} ) );
jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

describe( 'WCPayAPI', () => {
	describe( 'getStripe', () => {
		afterEach( () => {
			jest.useRealTimers();
			window.Stripe = undefined;
		} );

		test( 'waits for Stripe to be available in the global scope', async () => {
			jest.useFakeTimers();
			const api = new WCPayAPI( {}, request );
			let stripeInstance = null;

			api.getStripe().then( ( result ) => {
				stripeInstance = result;
			} );

			jest.runOnlyPendingTimers();
			await Promise.resolve();

			expect( stripeInstance ).toBeNull();

			window.Stripe = function Stripe() {};

			jest.runOnlyPendingTimers();
			await Promise.resolve();

			jest.runOnlyPendingTimers();
			await Promise.resolve();

			expect( stripeInstance ).toBeInstanceOf( window.Stripe );
		} );

		test( 'resolves immediately if Stripe is already available', async () => {
			const api = new WCPayAPI( {}, request );
			window.Stripe = function Stripe() {};
			const stripeInstance = await api.getStripe();
			expect( stripeInstance ).toBeInstanceOf( window.Stripe );
		} );
	} );

	describe( 'confirmIntent', () => {
		const payForOrderUrls = [
			'/checkout/order-pay/456/#wcpay-confirm-pi:123:secret:nonce',
			'/?page_id=7&order-pay=456&pay_for_order=true&key=key#wcpay-confirm-pi:123:secret:nonce',
		];

		beforeEach( () => {
			getConfig.mockImplementation( ( key ) => {
				if ( key === 'ajaxUrl' ) {
					return '/ajax';
				}
				return null;
			} );
		} );

		test.each( payForOrderUrls )(
			'uses the order ID paired with the nonce for %s',
			async ( redirectUrl ) => {
				const apiRequest = jest
					.fn()
					.mockResolvedValue( { return_url: '/success' } );
				const handleNextAction = jest.fn().mockResolvedValue( {
					paymentIntent: { id: 'pi_test' },
				} );
				const api = new WCPayAPI( {}, apiRequest );
				api.getStripe = jest
					.fn()
					.mockResolvedValue( { handleNextAction } );

				await api.confirmIntent( redirectUrl );

				expect( apiRequest ).toHaveBeenCalledWith( '/ajax', {
					action: 'update_order_status',
					order_id: '123',
					_ajax_nonce: 'nonce',
					intent_id: 'pi_test',
					should_save_payment_method: 'false',
					is_changing_payment: 'false',
				} );
			}
		);
	} );
} );
