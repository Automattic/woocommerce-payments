/**
 * Internal dependencies
 */
import { resolveWoopayAppearance } from '../resolve';
import { getConfig } from 'wcpay/utils/checkout';
import { getAppearance } from 'checkout/upe-styles';
import { getAppearanceType } from 'wcpay/checkout/utils';
import {
	isSupportedThemeEntrypoint,
	isShortcodeCheckout,
} from 'wcpay/checkout/woopay/utils';
import { maybePersistWoopayAppearance } from 'wcpay/checkout/woopay/appearance/persist';

jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );
jest.mock( 'checkout/upe-styles', () => ( {
	getAppearance: jest.fn(),
} ) );
jest.mock( 'wcpay/checkout/utils', () => ( {
	getAppearanceType: jest.fn(),
} ) );
jest.mock( 'wcpay/checkout/woopay/utils', () => ( {
	isSupportedThemeEntrypoint: jest.fn(),
	isShortcodeCheckout: jest.fn(),
} ) );
jest.mock( 'wcpay/checkout/woopay/appearance/persist', () => ( {
	maybePersistWoopayAppearance: jest.fn(),
} ) );

describe( 'resolveWoopayAppearance', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		getAppearanceType.mockReturnValue( 'shortcode_checkout' );
		isSupportedThemeEntrypoint.mockReturnValue( true );
	} );

	it( 'returns null when global theme support is disabled', () => {
		getConfig.mockReturnValue( false );

		expect( resolveWoopayAppearance() ).toBeNull();
		expect( getAppearance ).not.toHaveBeenCalled();
	} );

	it( 'returns null when appearance type is not supported', () => {
		isSupportedThemeEntrypoint.mockReturnValue( false );
		getConfig.mockImplementation( ( key ) => {
			if ( key === 'isWooPayGlobalThemeSupportEnabled' ) return true;
			return null;
		} );

		expect( resolveWoopayAppearance() ).toBeNull();
	} );

	it( 'returns server appearance when available', () => {
		const serverAppearance = { theme: 'stripe' };
		getConfig.mockImplementation( ( key ) => {
			if ( key === 'isWooPayGlobalThemeSupportEnabled' ) return true;
			if ( key === 'woopayAppearance' ) return serverAppearance;
			return null;
		} );

		expect( resolveWoopayAppearance() ).toBe( serverAppearance );
		expect( getAppearance ).not.toHaveBeenCalled();
		expect( maybePersistWoopayAppearance ).not.toHaveBeenCalled();
	} );

	it( 'falls back to DOM extraction on shortcode checkout and persists', () => {
		const domAppearance = { theme: 'night' };
		getConfig.mockImplementation( ( key ) => {
			if ( key === 'isWooPayGlobalThemeSupportEnabled' ) return true;
			if ( key === 'woopayAppearance' ) return null;
			return null;
		} );
		isShortcodeCheckout.mockReturnValue( true );
		getAppearance.mockReturnValue( domAppearance );

		expect( resolveWoopayAppearance() ).toBe( domAppearance );
		expect( getAppearance ).toHaveBeenCalledWith(
			'shortcode_checkout',
			true
		);
		expect( maybePersistWoopayAppearance ).toHaveBeenCalledWith(
			domAppearance
		);
	} );

	it( 'returns null when no server appearance and not on shortcode checkout', () => {
		getConfig.mockImplementation( ( key ) => {
			if ( key === 'isWooPayGlobalThemeSupportEnabled' ) return true;
			if ( key === 'woopayAppearance' ) return null;
			return null;
		} );
		isShortcodeCheckout.mockReturnValue( false );

		expect( resolveWoopayAppearance() ).toBeNull();
		expect( getAppearance ).not.toHaveBeenCalled();
	} );
} );
