/**
 * Internal dependencies
 */
import { normalizeOrderData } from '../normalize';

describe( 'normalize', () => {
	it( 'Should add - to billing_last_name if billing address has no last name', () => {
		const paymentDataMock = {
			billing_details: {
				name: 'Test',
			},
		};

		expect(
			normalizeOrderData( { paymentMethod: paymentDataMock } )
				.billing_last_name
		).toBe( '-' );
	} );

	it( 'Should have billing_last_name if billing address has last name', () => {
		const paymentDataMock = {
			billing_details: {
				name: 'Test Automated',
			},
		};

		expect(
			normalizeOrderData( { paymentMethod: paymentDataMock } )
				.billing_last_name
		).toBe( 'Automated' );
	} );
} );
