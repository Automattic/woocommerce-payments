/** @format */
/**
 * Internal dependencies
 */
import { composeTaxString } from '../map-events';

// Mock wcpaySettings
global.wcpaySettings = {
	zeroDecimalCurrencies: [ 'jpy', 'vnd' ],
	currencyData: {
		US: {
			code: 'USD',
			symbol: '$',
			symbolPosition: 'left',
			thousandSeparator: ',',
			decimalSeparator: '.',
			precision: 2,
		},
		EU: {
			code: 'EUR',
			symbol: '€',
			symbolPosition: 'left',
			thousandSeparator: '.',
			decimalSeparator: ',',
			precision: 2,
		},
	},
};

// Mock the tax descriptions module
jest.mock( '../../utils/tax-descriptions', () => ( {
	getLocalizedTaxDescription: ( description ) => {
		const mockTranslations = {
			'ES VAT': 'ES IVA',
			'FR VAT': 'FR TVA',
			'DE VAT': 'DE MwSt',
		};
		return mockTranslations[ description ] || description;
	},
} ) );

describe( 'composeTaxString', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [ 'jpy', 'vnd' ],
			connect: {
				country: 'US',
			},
			currencyData: {
				US: {
					code: 'USD',
					symbol: '$',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
				FR: {
					code: 'EUR',
					symbol: '€',
					symbolPosition: 'right_space',
					thousandSeparator: ' ',
					decimalSeparator: ',',
					precision: 2,
				},
			},
		};
	} );

	it( 'should return empty string when no tax data is present', () => {
		const event = {};
		expect( composeTaxString( event ) ).toBe( '' );
	} );

	it( 'should return empty string when fee_rates is present but no tax data', () => {
		const event = {
			fee_rates: {},
		};
		expect( composeTaxString( event ) ).toBe( '' );
	} );

	it( 'should return empty string when tax amount is zero', () => {
		const event = {
			fee_rates: {
				tax: {
					amount: 0,
					currency: 'EUR',
					percentage_rate: 0.21,
					description: 'ES VAT',
				},
			},
		};
		expect( composeTaxString( event ) ).toBe( '' );
	} );

	it( 'should format tax with localized description and percentage', () => {
		const event = {
			fee_rates: {
				tax: {
					amount: 10,
					currency: 'EUR',
					percentage_rate: 0.21,
					description: 'ES VAT',
				},
			},
		};
		expect( composeTaxString( event ) ).toBe(
			'Tax ES IVA (21.00%): -€0.10'
		);
	} );

	it( 'should format tax with just percentage when no description', () => {
		const event = {
			fee_rates: {
				tax: {
					amount: 10,
					currency: 'EUR',
					percentage_rate: 0.21,
				},
			},
		};
		expect( composeTaxString( event ) ).toBe( 'Tax (21.00%): -€0.10' );
	} );

	it( 'should format tax with just localized description when no percentage', () => {
		const event = {
			fee_rates: {
				tax: {
					amount: 10,
					currency: 'EUR',
					description: 'ES VAT',
				},
			},
		};
		expect( composeTaxString( event ) ).toBe( 'Tax ES IVA: -€0.10' );
	} );

	it( 'should handle different currencies', () => {
		const event = {
			fee_rates: {
				tax: {
					amount: 100,
					currency: 'USD',
					percentage_rate: 0.15,
					description: 'FR VAT',
				},
			},
		};
		expect( composeTaxString( event ) ).toBe(
			'Tax FR TVA (15.00%): -$1.00'
		);
	} );
} );
