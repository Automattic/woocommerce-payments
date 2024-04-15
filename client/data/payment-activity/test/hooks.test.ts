/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { usePaymentActivityData } from '../hooks';

jest.mock( '@wordpress/data' );

describe( 'usePaymentActivityData', () => {
	test( 'should return the correct payment activity data and loading state', () => {
		const mockPaymentActivityData = {
			total_payment_volume: 2500,
			charges: 3000,
			fees: 300,
			disputes: 315,
			refunds: 200,
		};

		const getPaymentActivityData = jest
			.fn()
			.mockReturnValue( mockPaymentActivityData );
		const isResolving = jest.fn().mockReturnValue( false );
		const select = jest.fn().mockReturnValue( {
			getPaymentActivityData,
			isResolving,
		} );
		( useSelect as jest.Mock ).mockImplementation( ( callback ) =>
			callback( select )
		);

		const result = usePaymentActivityData( {} );
		expect( result ).toEqual( {
			paymentActivityData: mockPaymentActivityData,
			isLoading: false,
		} );
	} );
} );
