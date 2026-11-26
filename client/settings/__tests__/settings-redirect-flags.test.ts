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

	it( 'reads the current page query by default', () => {
		mockGetQuery.mockReturnValue( {
			'woopayments-flag-rates-update': '1',
		} as ReturnType< typeof getQuery > );

		expect( getSettingsRedirectFlags() ).toEqual( { 'rates-update': '1' } );
	} );
} );
