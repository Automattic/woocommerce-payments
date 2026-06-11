/**
 * Internal dependencies
 */
import { resolveProductType } from '../resolve-product-type';

describe( 'resolveProductType', () => {
	it( 'prefers metadata.__product_type over suggested', () => {
		expect(
			resolveProductType(
				{ __product_type: 'digital_product_or_service' },
				'physical_product',
				false
			)
		).toBe( 'digital_product_or_service' );
	} );

	it( 'falls back to suggested when metadata is empty', () => {
		expect( resolveProductType( {}, 'physical_product', false ) ).toBe(
			'physical_product'
		);
	} );

	it( 'returns "" when both sources are empty', () => {
		expect( resolveProductType( null, null, false ) ).toBe( '' );
		expect( resolveProductType( undefined, undefined, false ) ).toBe( '' );
		expect( resolveProductType( {}, '', false ) ).toBe( '' );
	} );

	it( 'normalizes "multiple" to "other" when the flag is on', () => {
		expect(
			resolveProductType( { __product_type: 'multiple' }, null, true )
		).toBe( 'other' );
		expect( resolveProductType( {}, 'multiple', true ) ).toBe( 'other' );
	} );

	it( 'keeps "multiple" when the flag is off (legacy form path)', () => {
		expect(
			resolveProductType( { __product_type: 'multiple' }, null, false )
		).toBe( 'multiple' );
	} );

	it( 'does not normalize non-multiple values', () => {
		expect(
			resolveProductType(
				{ __product_type: 'physical_product' },
				null,
				true
			)
		).toBe( 'physical_product' );
	} );
} );
