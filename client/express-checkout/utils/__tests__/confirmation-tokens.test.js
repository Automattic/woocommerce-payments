/**
 * Internal dependencies
 */
import { shouldUseConfirmationTokens } from '../confirmation-tokens';

describe( 'shouldUseConfirmationTokens', () => {
	afterEach( () => {
		delete window.wcpayExpressCheckoutParams;
	} );

	test( 'returns true when flag is true', () => {
		window.wcpayExpressCheckoutParams = {
			flags: { isEceUsingConfirmationTokens: true },
		};

		expect( shouldUseConfirmationTokens() ).toBe( true );
	} );

	test( 'returns false when flag is false', () => {
		window.wcpayExpressCheckoutParams = {
			flags: { isEceUsingConfirmationTokens: false },
		};

		expect( shouldUseConfirmationTokens() ).toBe( false );
	} );

	test( 'defaults to true when flags are absent', () => {
		window.wcpayExpressCheckoutParams = {};

		expect( shouldUseConfirmationTokens() ).toBe( true );
	} );

	test( 'defaults to true when params are not set', () => {
		expect( shouldUseConfirmationTokens() ).toBe( true );
	} );
} );
