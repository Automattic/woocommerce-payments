/**
 * Internal dependencies
 */
import { getWoopayCardBrands } from '../woopay-card-brands';

describe( 'getWoopayCardBrands', () => {
	test( 'includes all expected card brands with truthy icons', () => {
		const brands = getWoopayCardBrands();
		const names = brands.map( ( b ) => b.name );

		expect( names ).toEqual(
			expect.arrayContaining( [
				'visa',
				'mastercard',
				'amex',
				'discover',
				'jcb',
				'unionpay',
				'diners',
			] )
		);

		brands.forEach( ( brand ) => {
			expect( brand.component ).toBeTruthy();
		} );
	} );
} );
