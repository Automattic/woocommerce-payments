/**
 * Internal dependencies
 */
import { getExpressCheckoutData } from '..';

describe( 'getExpressCheckoutData', () => {
	afterEach( () => {
		delete window.wcpayExpressCheckoutParams;
	} );

	test( 'returns null for missing option', () => {
		expect( getExpressCheckoutData( 'ajax_url' ) ).toBeNull();
	} );

	test( 'returns correct value for present option', () => {
		window.wcpayExpressCheckoutParams = {
			ajax_url: 'test',
		};

		expect( getExpressCheckoutData( 'ajax_url' ) ).toBe( 'test' );
	} );
} );
