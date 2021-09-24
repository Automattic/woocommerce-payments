/**
 * Internal dependencies
 */
import {
	ConcatenateCurrencyStrings,
	StringRepresentationOfCurrency,
} from '../utils';

const availableCurrencies = {
	USD: {
		code: 'USD',
		rate: 1,
		name: 'United States (US) dollar',
		id: 'usd',
		is_default: true,
		flag: '🇺🇸',
		symbol: '$',
	},
	CAD: {
		code: 'CAD',
		rate: '1.206823',
		name: 'Canadian dollar',
		id: 'cad',
		is_default: false,
		flag: '🇨🇦',
		symbol: '$',
	},
	GBP: {
		code: 'GBP',
		rate: '0.708099',
		name: 'Pound sterling',
		id: 'gbp',
		is_default: false,
		flag: '🇬🇧',
		symbol: '£',
	},
	EUR: {
		code: 'EUR',
		rate: '0.826381',
		name: 'Euro',
		id: 'eur',
		is_default: false,
		flag: '🇪🇺',
		symbol: '€',
	},
	AED: {
		code: 'AED',
		rate: '3.6732',
		name: 'United Arab Emirates dirham',
		id: 'aed',
		is_default: false,
		flag: '🇦🇪',
		symbol: 'د.إ',
	},
	CDF: {
		code: 'CDF',
		rate: '2000',
		name: 'Congolese franc',
		id: 'cdf',
		is_default: false,
		flag: '🇨🇩',
		symbol: 'Fr',
	},
	AUD: {
		code: 'AUD',
		rate: 1.79,
		name: 'Australian dollar',
		id: 'aud',
		is_default: false,
		flag: '🇦🇺',
		symbol: '$',
	},
	JPY: {
		code: 'JPY',
		rate: 1,
		name: 'Japanese yen',
		id: 'jpy',
		is_default: false,
		flag: '🇯🇵',
		symbol: '¥',
	},
	INR: {
		code: 'INR',
		rate: 1,
		name: 'Indian rupee',
		id: 'inr',
		is_default: false,
		flag: '🇮🇳',
		symbol: '₹',
		is_zero_decimal: false,
		last_updated: 1630070442,
	},
	DKK: {
		code: 'DKK',
		rate: '6.144615',
		name: 'Danish krone',
		id: 'dkk',
		is_default: false,
		flag: '🇩🇰',
		symbol: 'DKK',
	},
	BIF: {
		code: 'BIF',
		rate: '1974',
		name: 'Burundian franc',
		id: 'bif',
		is_default: false,
		flag: '🇧🇮',
		symbol: 'Fr',
	},
	CLP: {
		code: 'CLP',
		rate: '706.8',
		name: 'Chilean peso',
		id: 'clp',
		is_default: false,
		flag: '🇨🇱',
		symbol: '$',
	},
};

describe( 'Multi-currency setup utility functions', () => {
	it( 'should represent the currencies correctly', () => {
		const expectationMap = {
			USD: 'United States (US) dollar ($ USD)',
			JPY: 'Japanese yen (¥ JPY)',
			DKK: 'Danish krone (DKK)',
			EUR: 'Euro (€ EUR)',
			CLP: 'Chilean peso ($ CLP)',
			XXX: '',
			undefined: '',
			false: '',
		};
		Object.keys( expectationMap ).forEach( ( currency ) => {
			expect(
				StringRepresentationOfCurrency(
					availableCurrencies[ currency ]
				)
			).toEqual( expectationMap[ currency ] );
		} );
	} );

	it( 'should concatenate the string representations right', () => {
		const availableCurrenciesCopy = Object.assign(
			{},
			availableCurrencies
		);
		const results = [];
		for ( let runCount = 0; 5 > runCount; runCount++ ) {
			results.push(
				ConcatenateCurrencyStrings(
					Object.keys( availableCurrencies ).slice( 0, runCount ),
					'GBP',
					availableCurrencies
				)
			);
			expect( availableCurrenciesCopy ).toEqual( availableCurrencies );
		}
		expect( results[ 0 ] ).toEqual( '' );
		expect( results[ 1 ] ).toEqual( 'United States (US) dollar ($ USD)' );
		expect( results[ 2 ] ).toEqual(
			'United States (US) dollar ($ USD) and Canadian dollar ($ CAD)'
		);
		// Should skip GBP as it's the except key
		expect( results[ 3 ] ).toEqual(
			'United States (US) dollar ($ USD) and Canadian dollar ($ CAD)'
		);
		expect( results[ 4 ] ).toEqual(
			'United States (US) dollar ($ USD), Canadian dollar ($ CAD), and Euro (€ EUR)'
		);
	} );
} );
