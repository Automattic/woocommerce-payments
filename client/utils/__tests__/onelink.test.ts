/**
 * Internal dependencies
 */
import { isOnelinkAccount } from '../onelink';

declare const global: {
	wcpaySettings: {
		accountStatus?: {
			country?: string;
		};
	};
};

describe( 'isOnelinkAccount', () => {
	const originalSettings = global.wcpaySettings;

	afterEach( () => {
		global.wcpaySettings = originalSettings;
	} );

	it( 'returns true for UK accounts', () => {
		global.wcpaySettings = { accountStatus: { country: 'GB' } };

		expect( isOnelinkAccount() ).toBe( true );
	} );

	it( 'returns false for other countries', () => {
		global.wcpaySettings = { accountStatus: { country: 'US' } };

		expect( isOnelinkAccount() ).toBe( false );
	} );

	it( 'returns false when the account country is unknown', () => {
		global.wcpaySettings = {};

		expect( isOnelinkAccount() ).toBe( false );
	} );
} );
