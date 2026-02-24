/**
 * Internal dependencies
 */
import { getExpressCheckoutButtonAppearance } from '..';

describe( 'getExpressCheckoutButtonAppearance', () => {
	afterEach( () => {
		delete window.wcpayExpressCheckoutParams;
	} );

	test( 'uses default border radius when no settings or attributes', () => {
		window.wcpayExpressCheckoutParams = {};

		const result = getExpressCheckoutButtonAppearance( undefined );

		expect( result.variables.borderRadius ).toBe( '4px' );
		expect( result.variables.spacingUnit ).toBe( '6px' );
	} );

	test( 'uses border radius from button settings', () => {
		window.wcpayExpressCheckoutParams = {
			button: { radius: 10 },
		};

		const result = getExpressCheckoutButtonAppearance( undefined );

		expect( result.variables.borderRadius ).toBe( '10px' );
	} );

	test( 'uses border radius from button attributes over settings', () => {
		window.wcpayExpressCheckoutParams = {
			button: { radius: 10 },
		};

		const result = getExpressCheckoutButtonAppearance( {
			height: '48',
			borderRadius: '20',
		} );

		expect( result.variables.borderRadius ).toBe( '20px' );
	} );
} );
