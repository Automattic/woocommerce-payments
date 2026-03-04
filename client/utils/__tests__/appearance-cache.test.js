/**
 * Internal dependencies
 */
import {
	getCachedAppearance,
	setCachedAppearance,
	getCachedTheme,
	dispatchAppearanceEvent,
} from '../appearance-cache';

describe( 'Appearance cache', () => {
	const mockAppearance = {
		variables: { colorBackground: '#fff' },
		theme: 'stripe',
		labels: 'floating',
		rules: {},
	};

	beforeEach( () => {
		localStorage.clear();
	} );

	describe( 'getCachedAppearance', () => {
		test( 'returns null when no cached value exists', () => {
			expect( getCachedAppearance( 'blocks_checkout', 'v1' ) ).toBeNull();
		} );

		test( 'returns appearance when version matches', () => {
			localStorage.setItem(
				'wcpay_appearance_blocks_checkout',
				JSON.stringify( {
					version: 'v1',
					appearance: mockAppearance,
				} )
			);

			expect( getCachedAppearance( 'blocks_checkout', 'v1' ) ).toEqual(
				mockAppearance
			);
		} );

		test( 'returns null when version does not match', () => {
			localStorage.setItem(
				'wcpay_appearance_blocks_checkout',
				JSON.stringify( {
					version: 'v1',
					appearance: mockAppearance,
				} )
			);

			expect( getCachedAppearance( 'blocks_checkout', 'v2' ) ).toBeNull();
		} );

		test( 'returns null when stored data is corrupt', () => {
			localStorage.setItem(
				'wcpay_appearance_blocks_checkout',
				'not-valid-json'
			);

			expect( getCachedAppearance( 'blocks_checkout', 'v1' ) ).toBeNull();
		} );

		test( 'returns null when localStorage throws', () => {
			const original = Storage.prototype.getItem;
			Storage.prototype.getItem = jest.fn( () => {
				throw new Error( 'Access denied' );
			} );

			expect( getCachedAppearance( 'blocks_checkout', 'v1' ) ).toBeNull();

			Storage.prototype.getItem = original;
		} );
	} );

	describe( 'setCachedAppearance', () => {
		test( 'stores appearance with version in localStorage', () => {
			setCachedAppearance( 'blocks_checkout', 'v1', mockAppearance );

			const stored = JSON.parse(
				localStorage.getItem( 'wcpay_appearance_blocks_checkout' )
			);
			expect( stored ).toEqual( {
				version: 'v1',
				appearance: mockAppearance,
			} );
		} );

		test( 'uses location-specific cache keys', () => {
			setCachedAppearance( 'bnpl_product_page', 'v1', mockAppearance );

			expect(
				localStorage.getItem( 'wcpay_appearance_bnpl_product_page' )
			).not.toBeNull();
			expect(
				localStorage.getItem( 'wcpay_appearance_blocks_checkout' )
			).toBeNull();
		} );

		test( 'does not throw when localStorage is unavailable', () => {
			const original = Storage.prototype.setItem;
			Storage.prototype.setItem = jest.fn( () => {
				throw new Error( 'Quota exceeded' );
			} );

			expect( () => {
				setCachedAppearance( 'blocks_checkout', 'v1', mockAppearance );
			} ).not.toThrow();

			Storage.prototype.setItem = original;
		} );
	} );

	describe( 'getCachedTheme', () => {
		test( 'returns theme from cached appearance', () => {
			setCachedAppearance( 'blocks_checkout', 'v1', {
				variables: {},
				theme: 'night',
				labels: 'floating',
				rules: {},
			} );

			expect( getCachedTheme( 'blocks_checkout', 'v1' ) ).toBe( 'night' );
		} );

		test( 'returns null when no cached value exists', () => {
			expect( getCachedTheme( 'blocks_checkout', 'v1' ) ).toBeNull();
		} );

		test( 'returns null when version does not match', () => {
			setCachedAppearance( 'blocks_checkout', 'v1', {
				variables: {},
				theme: 'night',
				labels: 'floating',
				rules: {},
			} );

			expect( getCachedTheme( 'blocks_checkout', 'v2' ) ).toBeNull();
		} );
	} );

	describe( 'dispatchAppearanceEvent', () => {
		test( 'dispatches a CustomEvent with appearance and elementsLocation', () => {
			const handler = jest.fn();
			document.addEventListener( 'wcpay_elements_appearance', handler );

			const appearance = { theme: 'stripe', rules: {} };
			dispatchAppearanceEvent( appearance, 'blocks_checkout' );

			expect( handler ).toHaveBeenCalledTimes( 1 );
			const event = handler.mock.calls[ 0 ][ 0 ];
			expect( event.detail.appearance ).toBe( appearance );
			expect( event.detail.elementsLocation ).toBe( 'blocks_checkout' );

			document.removeEventListener(
				'wcpay_elements_appearance',
				handler
			);
		} );

		test( 'allows listeners to mutate the appearance object', () => {
			const handler = ( event ) => {
				event.detail.appearance.theme = 'night';
			};
			document.addEventListener( 'wcpay_elements_appearance', handler );

			const appearance = { theme: 'stripe', rules: {} };
			dispatchAppearanceEvent( appearance, 'blocks_checkout' );

			expect( appearance.theme ).toBe( 'night' );

			document.removeEventListener(
				'wcpay_elements_appearance',
				handler
			);
		} );
	} );
} );
