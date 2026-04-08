/**
 * Internal dependencies
 */
import { getLocalizedTaxDescription } from '../tax-descriptions';

describe( 'getLocalizedTaxDescription', () => {
	beforeEach( () => {
		// Reset the translation mock before each test
		jest.resetModules();
	} );

	it( 'should return localized description for known tax types', () => {
		expect( getLocalizedTaxDescription( 'ES VAT' ) ).toBe( 'ES VAT' );
		expect( getLocalizedTaxDescription( 'FR VAT' ) ).toBe( 'FR VAT' );
		expect( getLocalizedTaxDescription( 'DE VAT' ) ).toBe( 'DE VAT' );
	} );

	it( 'should return default tax description for unknown tax types', () => {
		expect( getLocalizedTaxDescription( 'UNKNOWN TAX' ) ).toBe( 'Tax' );
		expect( getLocalizedTaxDescription( '' ) ).toBe( 'Tax' );
	} );
} );
