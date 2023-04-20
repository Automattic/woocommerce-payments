/**
 * Internal dependencies
 */
import {
	showAuthenticationModalIfRequired,
	shouldSavePaymentPaymentMethod,
} from '../3ds-flow-handling';
import WCPayAPI from 'wcpay/checkout/api';
import { getConfig } from 'wcpay/utils/checkout';

jest.mock( 'wcpay/checkout/api' );
jest.mock( './config.js', () => ( {
	getConfig: jest.fn( () => 'generic error message' ),
} ) );
jest.mock( './paymentMethod.js', () => ( {
	shouldSavePaymentPaymentMethod: jest.fn( () => true ),
} ) );

describe( 'showAuthenticationModalIfRequired', () => {
	let apiRequest;
	let apiConfirmIntent;

	beforeEach( () => {
		apiRequest = jest.fn( () => Promise.resolve( 'redirect url' ) );
		apiConfirmIntent = jest.fn( () => ( {
			request: Promise.resolve( 'redirect url' ),
		} ) );
		WCPayAPI.mockImplementation( () => ( {
			confirmIntent: apiConfirmIntent,
		} ) );
		Object.defineProperty( window, 'location', {
			value: {
				href: 'https://example.com/checkout',
			},
			writable: true,
		} );
		document.body.innerHTML = `<form class="checkout"><div class="blockUI"></div></form>`;
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'should call confirmIntent with correct arguments and redirect to the URL returned by the API', async () => {
		const expectedConfirmation = {
			request: Promise.resolve( 'redirect url' ),
		};
		apiConfirmIntent.mockReturnValueOnce( expectedConfirmation );

		await showAuthenticationModalIfRequired();

		expect( apiConfirmIntent ).toHaveBeenCalledWith(
			'https://example.com/checkout',
			expect.anything()
		);
		expect( apiRequest ).not.toHaveBeenCalled();
		expect( document.body.innerHTML ).toBe(
			`<form class="checkout"></form>`
		);
		expect( window.location.href ).toBe( 'https://example.com/checkout' );
	} );

	it( 'should redirect to the URL returned by the API and not call request when confirmation is true', async () => {
		const expectedConfirmation = true;
		apiConfirmIntent.mockReturnValueOnce( expectedConfirmation );

		await showAuthenticationModalIfRequired();

		expect( apiConfirmIntent ).toHaveBeenCalledWith(
			'https://example.com/checkout',
			expect.anything()
		);
		expect( apiRequest ).not.toHaveBeenCalled();
		expect( document.body.innerHTML ).toBe(
			`<form class="checkout"></form>`
		);
		expect( window.location.href ).toBe( 'https://example.com/checkout' );
	} );

	it( 'should display an error message when request fails', async () => {
		const expectedError = new Error( 'Something went wrong' );
		apiConfirmIntent.mockReturnValueOnce( {
			request: Promise.reject( expectedError ),
		} );

		await showAuthenticationModalIfRequired();

		expect( apiConfirmIntent ).toHaveBeenCalledWith(
			'https://example.com/checkout',
			expect.anything()
		);
		expect( apiRequest ).not.toHaveBeenCalled();
		expect( document.body.innerHTML ).toBe(
			`<form class="checkout"></form>`
		);
		expect( window.location.href ).toBe( 'https://example.com/checkout' );
		expect( getConfig ).toHaveBeenCalledWith( 'genericErrorMessage' );
		expect( document.body.innerHTML ).toContain( 'generic error message' );
	} );

	it( 'should not save payment method when shouldSavePaymentPaymentMethod returns false', async () => {
		shouldSavePaymentPaymentMethod.mockReturnValueOnce( false );

		await showAuthenticationModalIfRequired();

		expect( apiConfirmIntent ).toHaveBeenCalledWith(
			'https://example.com/checkout',
			null
		);
	} );
} );
