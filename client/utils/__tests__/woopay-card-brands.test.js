/**
 * Internal dependencies
 */
import { wooPayCardBrands } from '../woopay-card-brands';

describe( 'wooPayCardBrands', () => {
	test( 'includes all expected card brands with truthy icons', () => {
		const names = wooPayCardBrands.map( ( b ) => b.name );

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

		wooPayCardBrands.forEach( ( brand ) => {
			expect( brand.component ).toBeTruthy();
		} );
	} );
} );
