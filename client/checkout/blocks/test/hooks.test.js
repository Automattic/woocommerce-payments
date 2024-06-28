/**
 * External dependencies
 */
import { renderHook, act } from '@testing-library/react-hooks';

/**
 * Internal dependencies
 */
import { usePaymentFailHandler, useFingerprint } from '../hooks';
import * as fingerprintModule from '../../utils/fingerprint';
// import { act } from '@testing-library/react';

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

	it( 'should return the correct failure response checkout processor payment failure', () => {
		const errorMessage = 'Your card was declined.';
		const paymentDetails = {
			errorMessage: errorMessage,
		};

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

describe( 'useFingerprint', () => {
	it( 'should return fingerprint', async () => {
		const mockVisitorId = 'test-visitor-id';
		const mockGetFingerprint = jest
			.fn()
			.mockResolvedValue( { visitorId: mockVisitorId } );

		jest.spyOn( fingerprintModule, 'getFingerprint' ).mockImplementation(
			mockGetFingerprint
		);

		let hook;

		await act( async () => {
			hook = renderHook( () => useFingerprint() );
		} );

		const [ fingerprint, error ] = hook.result.current;

		expect( mockGetFingerprint ).toHaveBeenCalledTimes( 1 );
		expect( fingerprint ).toBe( mockVisitorId );
		expect( error ).toBeNull();
	} );

	it( 'should handle errors when getting fingerprint fails', async () => {
		const mockError = new Error( 'Test error' );
		const mockGetFingerprint = jest.fn().mockRejectedValue( mockError );

		jest.spyOn( fingerprintModule, 'getFingerprint' ).mockImplementation(
			mockGetFingerprint
		);

		let hook;

		await act( async () => {
			hook = renderHook( () => useFingerprint() );
		} );

		const [ fingerprint, error ] = hook.result.current;

		expect( mockGetFingerprint ).toHaveBeenCalledTimes( 1 );
		expect( fingerprint ).toBe( '' );
		expect( error ).toBe( mockError.message );
	} );

	it( 'should use generic error message when error has no message', async () => {
		const mockGetFingerprint = jest.fn().mockRejectedValue( {} );

		jest.spyOn( fingerprintModule, 'getFingerprint' ).mockImplementation(
			mockGetFingerprint
		);

		let hook;

		await act( async () => {
			hook = renderHook( () => useFingerprint() );
		} );

		const [ fingerprint, error ] = hook.result.current;

		expect( mockGetFingerprint ).toHaveBeenCalledTimes( 1 );
		expect( fingerprint ).toBe( '' );
		expect( error ).toBe( fingerprintModule.FINGERPRINT_GENERIC_ERROR );
	} );
} );
