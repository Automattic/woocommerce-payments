/**
 * Internal dependencies
 */
import getCurrencyRemovalImpact from '../get-currency-removal-impact';

describe( 'getCurrencyRemovalImpact', () => {
	const enabledCodes = [ 'USD', 'GBP', 'EUR' ];

	test( 'marks a method as unavailable when the removed currency is its only enabled support', () => {
		const map = {
			ideal: { title: 'iDEAL', currencies: [ 'EUR' ] },
		};

		const impact = getCurrencyRemovalImpact( map, 'EUR', enabledCodes );

		expect( impact ).toEqual( { unavailable: [ 'ideal' ], limited: [] } );
	} );

	test( 'marks a method as limited when it still supports another enabled currency', () => {
		const map = {
			amazon_pay: {
				title: 'Amazon Pay',
				currencies: [ 'USD', 'GBP', 'EUR', 'ZAR' ],
			},
		};

		const impact = getCurrencyRemovalImpact( map, 'EUR', enabledCodes );

		expect( impact ).toEqual( {
			unavailable: [],
			limited: [ 'amazon_pay' ],
		} );
	} );

	test( 'ignores methods that do not support the removed currency', () => {
		const map = {
			bancontact: { title: 'Bancontact', currencies: [ 'EUR' ] },
			becs: { title: 'BECS', currencies: [ 'AUD' ] },
		};

		const impact = getCurrencyRemovalImpact( map, 'EUR', enabledCodes );

		expect( impact ).toEqual( {
			unavailable: [ 'bancontact' ],
			limited: [],
		} );
	} );

	test( 'treats a remaining supported currency as relevant only when it is enabled', () => {
		const map = {
			klarna: {
				title: 'Klarna',
				currencies: [ 'EUR', 'SEK' ],
			},
		};

		// SEK is supported by Klarna but not in the enabled set, so removing EUR
		// leaves Klarna with no enabled currency.
		const impact = getCurrencyRemovalImpact( map, 'EUR', enabledCodes );

		expect( impact ).toEqual( { unavailable: [ 'klarna' ], limited: [] } );
	} );

	test( 'splits a mixed map into both tiers', () => {
		const map = {
			ideal: { title: 'iDEAL', currencies: [ 'EUR' ] },
			amazon_pay: {
				title: 'Amazon Pay',
				currencies: [ 'USD', 'EUR' ],
			},
		};

		const impact = getCurrencyRemovalImpact( map, 'EUR', enabledCodes );

		expect( impact ).toEqual( {
			unavailable: [ 'ideal' ],
			limited: [ 'amazon_pay' ],
		} );
	} );

	test( 'returns empty tiers for a missing or empty map', () => {
		expect(
			getCurrencyRemovalImpact( undefined, 'EUR', enabledCodes )
		).toEqual( { unavailable: [], limited: [] } );
		expect( getCurrencyRemovalImpact( {}, 'EUR', enabledCodes ) ).toEqual( {
			unavailable: [],
			limited: [],
		} );
	} );
} );
