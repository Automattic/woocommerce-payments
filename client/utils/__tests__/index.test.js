/** @format */

/**
 * Internal dependencies
 */
import {
	getDocumentUrl,
	getPaymentMethodSettingsUrl,
	hasTestAccount,
	hasSandboxAccount,
} from '..';

describe( 'Utilities', () => {
	test( 'payment method settings link matches expected', () => {
		expect( getPaymentMethodSettingsUrl( 'foo' ) ).toEqual(
			'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&method=foo'
		);
	} );

	test( 'document url matches the expected URL', () => {
		expect( getDocumentUrl( 'documentID' ) ).toEqual(
			'https://site.com/wp-json/wc/v3/payments/documents/documentID?_wpnonce=random_wp_rest_nonce'
		);
	} );
} );

describe( 'hasTestAccount', () => {
	const originalWcpaySettings = global.wcpaySettings;

	afterEach( () => {
		global.wcpaySettings = originalWcpaySettings;
	} );

	it( 'returns false when wcpaySettings is undefined', () => {
		global.wcpaySettings = undefined;
		expect( hasTestAccount() ).toBe( false );
	} );

	it( 'returns false when wcpaySettings is null', () => {
		global.wcpaySettings = null;
		expect( hasTestAccount() ).toBe( false );
	} );

	it( 'returns false when account is not connected', () => {
		global.wcpaySettings = {
			isAccountConnected: false,
			accountStatus: { isLive: false, testDrive: true },
		};
		expect( hasTestAccount() ).toBe( false );
	} );

	it( 'returns false when accountStatus is not an object', () => {
		global.wcpaySettings = {
			isAccountConnected: true,
			accountStatus: null,
		};
		expect( hasTestAccount() ).toBe( false );
	} );

	it( 'returns true when account is not live and testDrive is true', () => {
		global.wcpaySettings = {
			isAccountConnected: true,
			accountStatus: { isLive: false, testDrive: true },
		};
		expect( hasTestAccount() ).toBe( true );
	} );

	it( 'returns true when isLive is undefined and testDrive is true', () => {
		global.wcpaySettings = {
			isAccountConnected: true,
			accountStatus: { testDrive: true },
		};
		expect( hasTestAccount() ).toBe( true );
	} );

	it( 'returns false when account is live', () => {
		global.wcpaySettings = {
			isAccountConnected: true,
			accountStatus: { isLive: true, testDrive: true },
		};
		expect( hasTestAccount() ).toBe( false );
	} );

	it( 'returns false when testDrive is false', () => {
		global.wcpaySettings = {
			isAccountConnected: true,
			accountStatus: { isLive: false, testDrive: false },
		};
		expect( hasTestAccount() ).toBe( false );
	} );

	it( 'returns false when testDrive is undefined', () => {
		global.wcpaySettings = {
			isAccountConnected: true,
			accountStatus: { isLive: false },
		};
		expect( hasTestAccount() ).toBe( false );
	} );
} );

describe( 'hasSandboxAccount', () => {
	const originalWcpaySettings = global.wcpaySettings;

	afterEach( () => {
		global.wcpaySettings = originalWcpaySettings;
	} );

	it( 'returns false when wcpaySettings is undefined', () => {
		global.wcpaySettings = undefined;
		expect( hasSandboxAccount() ).toBe( false );
	} );

	it( 'returns false when wcpaySettings is null', () => {
		global.wcpaySettings = null;
		expect( hasSandboxAccount() ).toBe( false );
	} );

	it( 'returns false when account is not connected', () => {
		global.wcpaySettings = {
			isAccountConnected: false,
			accountStatus: { isLive: false, testDrive: false },
		};
		expect( hasSandboxAccount() ).toBe( false );
	} );

	it( 'returns false when accountStatus is not an object', () => {
		global.wcpaySettings = {
			isAccountConnected: true,
			accountStatus: null,
		};
		expect( hasSandboxAccount() ).toBe( false );
	} );

	it( 'returns true when account is not live and testDrive is false', () => {
		global.wcpaySettings = {
			isAccountConnected: true,
			accountStatus: { isLive: false, testDrive: false },
		};
		expect( hasSandboxAccount() ).toBe( true );
	} );

	it( 'returns true when isLive is undefined and testDrive is false', () => {
		global.wcpaySettings = {
			isAccountConnected: true,
			accountStatus: { testDrive: false },
		};
		expect( hasSandboxAccount() ).toBe( true );
	} );

	it( 'returns true when isLive is undefined and testDrive is undefined', () => {
		global.wcpaySettings = {
			isAccountConnected: true,
			accountStatus: {},
		};
		expect( hasSandboxAccount() ).toBe( true );
	} );

	it( 'returns false when account is live', () => {
		global.wcpaySettings = {
			isAccountConnected: true,
			accountStatus: { isLive: true, testDrive: false },
		};
		expect( hasSandboxAccount() ).toBe( false );
	} );

	it( 'returns false when testDrive is true', () => {
		global.wcpaySettings = {
			isAccountConnected: true,
			accountStatus: { isLive: false, testDrive: true },
		};
		expect( hasSandboxAccount() ).toBe( false );
	} );
} );
