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
		expect( getLocalizedTaxDescription( 'ES VAT' ) ).toBe( 'ES IVA' );
		expect( getLocalizedTaxDescription( 'FR VAT' ) ).toBe( 'FR TVA' );
		expect( getLocalizedTaxDescription( 'DE VAT' ) ).toBe( 'DE MwSt' );
	} );

	it( 'should handle Japanese Consumption Tax correctly', () => {
		expect( getLocalizedTaxDescription( 'JP JCT' ) ).toBe( 'JP 消費税' );
	} );

	it( 'should handle multi-language tax names', () => {
		expect( getLocalizedTaxDescription( 'BE VAT' ) ).toBe( 'BE TVA/BTW' );
		expect( getLocalizedTaxDescription( 'CH VAT' ) ).toBe(
			'CH MWST/TVA/IVA'
		);
	} );

	it( 'should handle GST countries', () => {
		expect( getLocalizedTaxDescription( 'AU GST' ) ).toBe( 'AU GST' );
		expect( getLocalizedTaxDescription( 'NZ GST' ) ).toBe( 'NZ GST' );
		expect( getLocalizedTaxDescription( 'SG GST' ) ).toBe( 'SG GST' );
	} );

	it( 'should handle special characters in tax names', () => {
		expect( getLocalizedTaxDescription( 'GR VAT' ) ).toBe( 'GR ΦΠΑ' );
		expect( getLocalizedTaxDescription( 'HU VAT' ) ).toBe( 'HU ÁFA' );
	} );

	it( 'should return default tax description for unknown tax types', () => {
		expect( getLocalizedTaxDescription( 'UNKNOWN TAX' ) ).toBe( 'Tax' );
		expect( getLocalizedTaxDescription( '' ) ).toBe( 'Tax' );
	} );
} );
