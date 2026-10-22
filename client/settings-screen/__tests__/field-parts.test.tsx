/**
 * Internal dependencies
 */
import { expressCheckoutLocation } from '../field-parts';

describe( 'expressCheckoutLocation', () => {
	const parts = expressCheckoutLocation( 'woopay', 'cart' );

	it( 'is on when the method is in the location’s methods', () => {
		expect(
			parts.getValue?.( {
				item: { express_checkout_cart_methods: [ 'woopay' ] },
			} )
		).toBe( true );
		expect( parts.getValue?.( { item: {} } ) ).toBe( false );
	} );

	it( 'adds or removes the method and keeps the others', () => {
		const item = {
			express_checkout_cart_methods: [ 'payment_request', 'woopay' ],
		};

		expect( parts.setValue?.( { item, value: false } ) ).toEqual( {
			express_checkout_cart_methods: [ 'payment_request' ],
		} );
		expect(
			parts.setValue?.( {
				item: { express_checkout_cart_methods: [ 'payment_request' ] },
				value: true,
			} )
		).toEqual( {
			express_checkout_cart_methods: [ 'payment_request', 'woopay' ],
		} );
	} );
} );
