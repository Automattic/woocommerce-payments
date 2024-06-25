/**
 * External dependencies
 */
import { renderHook } from '@testing-library/react-hooks';

/**
 * Internal dependencies
 */
import { usePaymentFailHandler } from '../hooks';

describe( 'usePaymentFailHandler', () => {
	let mockOnCheckoutFail;
	let mockEmitResponse;

	beforeEach( () => {
		mockOnCheckoutFail = jest.fn();
		mockEmitResponse = {
			noticeContexts: {
				PAYMENTS: 'payments_context',
			},
		};
	} );

	it( 'should emit the correct failure response on payment failure', () => {
		const errorMessage = 'Your card was declined.';
		const paymentDetails = {
			errorMessage: errorMessage,
		};

		mockOnCheckoutFail.mockImplementation( ( callback ) => {
			callback( { processingResponse: { paymentDetails } } );
		} );

		renderHook( () =>
			usePaymentFailHandler( mockOnCheckoutFail, mockEmitResponse )
		);

		expect( mockOnCheckoutFail ).toHaveBeenCalled();
		const failureResponse = mockOnCheckoutFail.mock.calls[ 0 ][ 0 ]( {
			processingResponse: { paymentDetails },
		} );

		expect( failureResponse ).toEqual( {
			type: 'failure',
			message: errorMessage,
			messageContext: 'payments_context',
		} );
	} );
} );
