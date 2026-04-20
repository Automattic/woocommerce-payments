/**
 * Jest environment configuration
 */

/**
 * Internal dependencies
 */
import { getCardBrands } from '../card-brands';
import { getUPEConfig } from 'wcpay/utils/checkout';

// Mock the getUPEConfig function
jest.mock( 'wcpay/utils/checkout', () => ( {
	getUPEConfig: jest.fn(),
} ) );

// Mock the global wcpaySettings will be set in each test

describe( 'getCardBrands', () => {
	test( 'returns base brands for non-France merchants', () => {
		global.window.wcpaySettings = {
			accountStatus: {
				country: 'US',
			},
		};
		// Mock getUPEConfig to return null
		getUPEConfig.mockReturnValue( null );

		const brands = getCardBrands();

		// Should include the 4 base brands plus JCB and CUP
		expect( brands ).toHaveLength( 6 );
		expect( brands.map( ( b ) => b.name ) ).toEqual( [
			'visa',
			'mastercard',
			'amex',
			'discover',
			'jcb',
			'unionpay',
		] );
	} );

	test( 'includes CB for France merchants', () => {
		global.window.wcpaySettings = {
			accountStatus: {
				country: 'FR',
			},
		};
		// Mock getUPEConfig to return France
		getUPEConfig.mockReturnValue( 'FR' );

		const brands = getCardBrands();

		// Should include all brands including CB
		expect( brands ).toHaveLength( 7 );
		expect( brands.map( ( b ) => b.name ) ).toEqual( [
			'visa',
			'mastercard',
			'amex',
			'discover',
			'jcb',
			'unionpay',
			'cartes_bancaires',
		] );
	} );

	test( 'handles missing account status gracefully', () => {
		global.window.wcpaySettings = {};
		// Mock getUPEConfig to return null
		getUPEConfig.mockReturnValue( null );

		const brands = getCardBrands();

		// Should still return base brands without CB
		expect( brands ).toHaveLength( 6 );
		expect( brands.map( ( b ) => b.name ) ).not.toContain(
			'cartes_bancaires'
		);
	} );

	test( 'handles missing wcpaySettings gracefully', () => {
		global.window.wcpaySettings = undefined;
		// Mock getUPEConfig to return null
		getUPEConfig.mockReturnValue( null );

		const brands = getCardBrands();

		// Should still return base brands without CB
		expect( brands ).toHaveLength( 6 );
		expect( brands.map( ( b ) => b.name ) ).not.toContain(
			'cartes_bancaires'
		);
	} );

	test( 'uses UPE config storeCountry when wcpaySettings is not available', () => {
		global.window.wcpaySettings = undefined;
		// Mock getUPEConfig to return France for storeCountry
		getUPEConfig.mockImplementation( ( key ) => {
			if ( key === 'storeCountry' ) {
				return 'FR';
			}
			return null;
		} );

		const brands = getCardBrands();

		// Check if getUPEConfig was called
		expect( getUPEConfig ).toHaveBeenCalledWith( 'storeCountry' );

		// Should include CB for France
		expect( brands ).toHaveLength( 7 );
		expect( brands.map( ( b ) => b.name ) ).toContain( 'cartes_bancaires' );
	} );
} );
