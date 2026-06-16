import {
	rememberElementCurrency,
	getElementCurrency,
	__resetElementCurrencyForTests,
} from '../element-currency-cache';

describe( 'element-currency-cache', () => {
	afterEach( () => {
		__resetElementCurrencyForTests();
	} );

	test( 'returns null when nothing has been set', () => {
		expect( getElementCurrency() ).toBeNull();
	} );

	test( 'returns the set value when one has been written', () => {
		rememberElementCurrency( 'usd' );

		expect( getElementCurrency() ).toBe( 'usd' );
	} );

	test( 'returns the stored value so it can be used inline', () => {
		expect( rememberElementCurrency( 'usd' ) ).toBe( 'usd' );
		expect( rememberElementCurrency( '' ) ).toBeNull();
	} );

	test( 'a falsy write reverts to null', () => {
		rememberElementCurrency( 'usd' );
		rememberElementCurrency( null );

		expect( getElementCurrency() ).toBeNull();
	} );

	test( 'an empty-string write reverts to null', () => {
		rememberElementCurrency( 'usd' );
		rememberElementCurrency( '' );

		expect( getElementCurrency() ).toBeNull();
	} );
} );
