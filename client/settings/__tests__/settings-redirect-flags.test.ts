/**
 * External dependencies
 */
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { getSettingsRedirectFlags } from '../settings-redirect-flags';

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn(),
} ) );

const mockGetQuery = getQuery as jest.MockedFunction< typeof getQuery >;

describe( 'getSettingsRedirectFlags', () => {
	it( 'returns prefixed flags without the prefix', () => {
		expect(
			getSettingsRedirectFlags( {
				page: 'wc-settings',
				anchor: '#payment-method-card',
				'woopayments-flag-rates-update': '1',
				'woopayments-flag-campaign': 'spring_2026',
			} )
		).toEqual( { 'rates-update': '1', campaign: 'spring_2026' } );
	} );

	it( 'ignores an empty flag name and non-string values', () => {
		expect(
			getSettingsRedirectFlags( {
				'woopayments-flag-': '1',
				'woopayments-flag-list': [ 'a', 'b' ],
			} )
		).toEqual( {} );
	} );

	it( 'treats names that exist on Object.prototype as plain flags', () => {
		const flags = getSettingsRedirectFlags( {
			'woopayments-flag-constructor': 'a',
			'woopayments-flag-hasOwnProperty': 'b',
			'woopayments-flag-__proto__': 'c',
		} );

		expect( flags.constructor ).toBe( 'a' );
		expect( flags.hasOwnProperty ).toBe( 'b' );
		expect( Object.keys( flags ).sort() ).toEqual( [
			'__proto__',
			'constructor',
			'hasOwnProperty',
		] );
		expect( ( {} as Record< string, unknown > ).polluted ).toBeUndefined();
	} );

	it( 'does not read prefixed names from the middle of a key', () => {
		expect(
			getSettingsRedirectFlags( { 'x-woopayments-flag-a': '1' } )
		).toEqual( {} );
	} );

	it( 'reads the current page query by default', () => {
		mockGetQuery.mockReturnValue( {
			'woopayments-flag-rates-update': '1',
		} as ReturnType< typeof getQuery > );

		expect( getSettingsRedirectFlags() ).toEqual( { 'rates-update': '1' } );
	} );
} );
