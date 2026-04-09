/**
 * Internal dependencies
 */
import { getWoopayCardBrands } from '../woopay-card-brands';

describe( 'getWoopayCardBrands', () => {
	test( 'returns 6 brands with truthy component values', () => {
		const brands = getWoopayCardBrands();

		expect( brands ).toHaveLength( 6 );
		expect( brands.map( ( b ) => b.name ) ).toEqual( [
			'visa',
			'mastercard',
			'amex',
			'discover',
			'jcb',
			'unionpay',
		] );

		brands.forEach( ( brand ) => {
			expect( brand.component ).toBeTruthy();
		} );
	} );
} );
