/**
 * Internal dependencies
 */
import {
	normalizeBrand,
	isValidPreferredCard,
	getCachedPreferredCard,
	setCachedPreferredCard,
	fetchPreferredCard,
} from '../preferred-card-utils';

// Mock WooPayUserConnect so fetchPreferredCard doesn't need a real iframe.
const mockGetPreferredPaymentMethod = jest.fn();
const mockDetachMessageListener = jest.fn();

jest.mock( 'wcpay/checkout/woopay/connect/user-connect', () => {
	return jest.fn().mockImplementation( () => ( {
		getPreferredPaymentMethod: mockGetPreferredPaymentMethod,
		detachMessageListener: mockDetachMessageListener,
	} ) );
} );

describe( 'preferred-card-utils', () => {
	describe( 'normalizeBrand', () => {
		test( 'maps american_express to amex', () => {
			expect( normalizeBrand( 'american_express' ) ).toBe( 'amex' );
		} );

		test( 'maps diners_club to diners', () => {
			expect( normalizeBrand( 'diners_club' ) ).toBe( 'diners' );
		} );

		test( 'maps china_unionpay to unionpay', () => {
			expect( normalizeBrand( 'china_unionpay' ) ).toBe( 'unionpay' );
		} );

		test( 'passes through brands that have no alias', () => {
			expect( normalizeBrand( 'visa' ) ).toBe( 'visa' );
			expect( normalizeBrand( 'mastercard' ) ).toBe( 'mastercard' );
			expect( normalizeBrand( 'discover' ) ).toBe( 'discover' );
			expect( normalizeBrand( 'jcb' ) ).toBe( 'jcb' );
		} );
	} );

	describe( 'isValidPreferredCard', () => {
		test( 'returns true for valid card', () => {
			expect(
				isValidPreferredCard( { brand: 'visa', last4: '4242' } )
			).toBe( true );
		} );

		test.each( [
			[ 'null', null ],
			[ 'undefined', undefined ],
			[ 'empty object', {} ],
			[ 'empty brand', { brand: '', last4: '4242' } ],
			[ 'non-string brand', { brand: 123, last4: '4242' } ],
			[ 'too short last4', { brand: 'visa', last4: '42' } ],
			[ 'too long last4', { brand: 'visa', last4: '42424' } ],
			[ 'non-numeric last4', { brand: 'visa', last4: 'abcd' } ],
			[ 'missing last4', { brand: 'visa' } ],
			[ 'missing brand', { last4: '4242' } ],
		] )( 'returns false for %s', ( _label, card ) => {
			expect( isValidPreferredCard( card ) ).toBeFalsy();
		} );
	} );

	describe( 'getCachedPreferredCard', () => {
		beforeEach( () => {
			localStorage.clear();
		} );

		test( 'returns cached card when valid', () => {
			localStorage.setItem(
				'woopay_preferred_card',
				JSON.stringify( { brand: 'visa', last4: '4242' } )
			);
			expect( getCachedPreferredCard() ).toEqual( {
				brand: 'visa',
				last4: '4242',
			} );
		} );

		test( 'returns null when cache is empty', () => {
			expect( getCachedPreferredCard() ).toBeNull();
		} );

		test( 'returns null for corrupted JSON', () => {
			localStorage.setItem( 'woopay_preferred_card', 'not valid json' );
			expect( getCachedPreferredCard() ).toBeNull();
		} );

		test( 'returns null for invalid card shape in cache', () => {
			localStorage.setItem(
				'woopay_preferred_card',
				JSON.stringify( { brand: 'visa' } )
			);
			expect( getCachedPreferredCard() ).toBeNull();
		} );

		test( 'returns null when localStorage throws', () => {
			jest.spyOn( Storage.prototype, 'getItem' ).mockImplementation(
				() => {
					throw new Error( 'private browsing' );
				}
			);
			expect( getCachedPreferredCard() ).toBeNull();
			Storage.prototype.getItem.mockRestore();
		} );
	} );

	describe( 'setCachedPreferredCard', () => {
		beforeEach( () => {
			localStorage.clear();
		} );

		test( 'writes valid card to localStorage', () => {
			setCachedPreferredCard( { brand: 'visa', last4: '4242' } );
			expect(
				JSON.parse( localStorage.getItem( 'woopay_preferred_card' ) )
			).toEqual( { brand: 'visa', last4: '4242' } );
		} );

		test( 'stores only brand and last4 (strips extra fields)', () => {
			setCachedPreferredCard( {
				brand: 'visa',
				last4: '4242',
				extra: 'should not persist',
			} );
			expect(
				JSON.parse( localStorage.getItem( 'woopay_preferred_card' ) )
			).toEqual( { brand: 'visa', last4: '4242' } );
		} );

		test( 'removes cache when called with null', () => {
			localStorage.setItem(
				'woopay_preferred_card',
				JSON.stringify( { brand: 'visa', last4: '4242' } )
			);
			setCachedPreferredCard( null );
			expect(
				localStorage.getItem( 'woopay_preferred_card' )
			).toBeNull();
		} );

		test( 'removes cache when called with invalid card', () => {
			localStorage.setItem(
				'woopay_preferred_card',
				JSON.stringify( { brand: 'visa', last4: '4242' } )
			);
			setCachedPreferredCard( { brand: 'visa' } );
			expect(
				localStorage.getItem( 'woopay_preferred_card' )
			).toBeNull();
		} );

		test( 'silently ignores when localStorage.setItem throws', () => {
			jest.spyOn( Storage.prototype, 'setItem' ).mockImplementation(
				() => {
					throw new Error( 'quota exceeded' );
				}
			);
			expect( () =>
				setCachedPreferredCard( {
					brand: 'visa',
					last4: '4242',
				} )
			).not.toThrow();
			Storage.prototype.setItem.mockRestore();
		} );

		test( 'silently ignores when localStorage.removeItem throws', () => {
			jest.spyOn( Storage.prototype, 'removeItem' ).mockImplementation(
				() => {
					throw new Error( 'private browsing' );
				}
			);
			expect( () => setCachedPreferredCard( null ) ).not.toThrow();
			Storage.prototype.removeItem.mockRestore();
		} );
	} );

	describe( 'fetchPreferredCard', () => {
		beforeEach( () => {
			mockGetPreferredPaymentMethod.mockReset();
			mockDetachMessageListener.mockReset();
		} );

		test( 'returns valid card from iframe', async () => {
			mockGetPreferredPaymentMethod.mockResolvedValue( {
				brand: 'mastercard',
				last4: '5555',
			} );
			const result = await fetchPreferredCard();
			expect( result ).toEqual( {
				brand: 'mastercard',
				last4: '5555',
			} );
			expect( mockDetachMessageListener ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'returns null when iframe returns invalid data', async () => {
			mockGetPreferredPaymentMethod.mockResolvedValue( {
				brand: 'visa',
			} );
			const result = await fetchPreferredCard();
			expect( result ).toBeNull();
			expect( mockDetachMessageListener ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'returns null when iframe returns null', async () => {
			mockGetPreferredPaymentMethod.mockResolvedValue( null );
			const result = await fetchPreferredCard();
			expect( result ).toBeNull();
			expect( mockDetachMessageListener ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'detaches listener even when query rejects', async () => {
			mockGetPreferredPaymentMethod.mockRejectedValue(
				new Error( 'timeout' )
			);
			await expect( fetchPreferredCard() ).rejects.toThrow( 'timeout' );
			expect( mockDetachMessageListener ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
