/**
 * Internal dependencies
 */
import { ExpressPaymentSession } from '../express-payment-session';
import {
	buildStripeElementsOptions,
	createPaymentCredential,
} from 'wcpay/express-checkout/utils';
import { getSetupFutureUsageForCart } from 'wcpay/express-checkout/utils/subscriptions';
import { transformCartDataForDisplayItems } from 'wcpay/express-checkout/transformers/wc-to-stripe';
import { validateElements } from 'wcpay/checkout/utils/validate-elements';

jest.mock( 'wcpay/express-checkout/utils', () => ( {
	// Passthrough so assertions can inspect the options the session assembles.
	buildStripeElementsOptions: jest.fn( ( options ) => options ),
	createPaymentCredential: jest.fn(),
} ) );

jest.mock( 'wcpay/express-checkout/utils/subscriptions', () => ( {
	getSetupFutureUsageForCart: jest.fn( () => null ),
} ) );

jest.mock( 'wcpay/express-checkout/transformers/wc-to-stripe', () => ( {
	transformCartDataForDisplayItems: jest.fn(),
} ) );

jest.mock( 'wcpay/checkout/utils/validate-elements', () => ( {
	validateElements: jest.fn().mockResolvedValue( undefined ),
} ) );

const baseConfig = ( overrides = {} ) => ( {
	method: 'googlePay',
	expressPaymentType: 'google_pay',
	stripePaymentMethodType: 'card',
	amount: 2399,
	currency: 'usd',
	useConfirmationTokens: true,
	isManualCapture: false,
	cartData: { totals: {}, items: [] },
	storeName: 'Test Store',
	needsPayerPhone: false,
	...overrides,
} );

describe( 'ExpressPaymentSession', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		getSetupFutureUsageForCart.mockReturnValue( null );
	} );

	describe( 'getElementsOptions', () => {
		it( 'assembles the Stripe Elements options for the single method type', () => {
			const session = new ExpressPaymentSession( baseConfig() );

			const options = session.getElementsOptions();

			expect( buildStripeElementsOptions ).toHaveBeenCalledWith(
				expect.objectContaining( {
					amount: 2399,
					currency: 'usd',
					useConfirmationTokens: true,
					paymentMethodTypes: [ 'card' ],
					captureMethod: undefined,
					setupFutureUsage: null,
				} )
			);
			expect( options.paymentMethodTypes ).toEqual( [ 'card' ] );
		} );

		it( 'requests manual capture when enabled', () => {
			const session = new ExpressPaymentSession(
				baseConfig( { isManualCapture: true } )
			);

			session.getElementsOptions();

			expect( buildStripeElementsOptions ).toHaveBeenCalledWith(
				expect.objectContaining( { captureMethod: 'manual' } )
			);
		} );

		it( 'carries the cart-derived setupFutureUsage', () => {
			getSetupFutureUsageForCart.mockReturnValue( 'off_session' );
			const session = new ExpressPaymentSession( baseConfig() );

			session.getElementsOptions();

			expect( buildStripeElementsOptions ).toHaveBeenCalledWith(
				expect.objectContaining( { setupFutureUsage: 'off_session' } )
			);
		} );

		it( 'omits the method type when none is configured', () => {
			const session = new ExpressPaymentSession(
				baseConfig( { stripePaymentMethodType: '' } )
			);

			session.getElementsOptions();

			expect( buildStripeElementsOptions ).toHaveBeenCalledWith(
				expect.objectContaining( { paymentMethodTypes: [] } )
			);
		} );
	} );

	describe( 'buildClickResolution', () => {
		it( 'never collects shipping and requires email', () => {
			transformCartDataForDisplayItems.mockReturnValue( [
				{ name: 'A product', amount: 2399 },
			] );
			const session = new ExpressPaymentSession( baseConfig() );

			const resolution = session.buildClickResolution();

			expect( resolution ).toEqual( {
				emailRequired: true,
				phoneNumberRequired: false,
				shippingAddressRequired: false,
				lineItems: [ { name: 'A product', amount: 2399 } ],
				business: { name: 'Test Store' },
			} );
		} );

		it( 'omits the business name when the store name is empty', () => {
			const session = new ExpressPaymentSession(
				baseConfig( { storeName: null } )
			);

			expect( session.buildClickResolution() ).not.toHaveProperty(
				'business'
			);
		} );

		it( 'requests the payer phone when configured', () => {
			const session = new ExpressPaymentSession(
				baseConfig( { needsPayerPhone: true } )
			);

			expect( session.buildClickResolution().phoneNumberRequired ).toBe(
				true
			);
		} );

		it( 'falls back to no line items when the cart is unavailable', () => {
			const session = new ExpressPaymentSession(
				baseConfig( { cartData: null } )
			);

			expect( session.buildClickResolution().lineItems ).toBeUndefined();
			expect( transformCartDataForDisplayItems ).not.toHaveBeenCalled();
		} );

		it( 'tolerates a transformer failure', () => {
			transformCartDataForDisplayItems.mockImplementation( () => {
				throw new Error( 'bad cart' );
			} );
			const session = new ExpressPaymentSession( baseConfig() );

			expect( session.buildClickResolution().lineItems ).toBeUndefined();
		} );
	} );

	describe( 'confirm', () => {
		const stripe = {};
		const elements = { submit: jest.fn() };

		it( 'validates, creates the credential, and normalizes the result', async () => {
			createPaymentCredential.mockResolvedValue( {
				id: 'ct_123',
				type: 'confirmation_token',
			} );
			const session = new ExpressPaymentSession( baseConfig() );

			const result = await session.confirm( stripe, elements );

			expect( validateElements ).toHaveBeenCalledWith( elements );
			expect( createPaymentCredential ).toHaveBeenCalledWith(
				stripe,
				elements,
				true
			);
			expect( result ).toEqual( {
				credentialId: 'ct_123',
				credentialType: 'confirmation_token',
				expressPaymentType: 'google_pay',
				stripePaymentMethodTypes: [ 'card' ],
			} );
		} );

		it( 'propagates a validation failure without creating a credential', async () => {
			validateElements.mockRejectedValueOnce(
				new Error( 'incomplete fields' )
			);
			const session = new ExpressPaymentSession( baseConfig() );

			await expect( session.confirm( stripe, elements ) ).rejects.toThrow(
				'incomplete fields'
			);
			expect( createPaymentCredential ).not.toHaveBeenCalled();
		} );

		it( 'propagates a credential failure', async () => {
			createPaymentCredential.mockRejectedValueOnce(
				new Error( 'declined' )
			);
			const session = new ExpressPaymentSession( baseConfig() );

			await expect( session.confirm( stripe, elements ) ).rejects.toThrow(
				'declined'
			);
		} );
	} );
} );
