/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { SavedTokenHandler } from '../saved-token-handler';

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn( () => ( {} ) ),
} ) );

const ERROR_MESSAGE = 'Error: Amount must be no more than $999,999.99';

describe( 'SavedTokenHandler', () => {
	it( 'shows the server error message when the saved card payment fails', () => {
		const onCheckoutFail = jest.fn();

		render(
			<SavedTokenHandler
				api={ {} }
				eventRegistration={ {
					onPaymentSetup: jest.fn(),
					onCheckoutSuccess: jest.fn(),
					onCheckoutFail,
				} }
				emitResponse={ {
					noticeContexts: { PAYMENTS: 'wc/payment-area' },
				} }
			/>
		);

		expect( onCheckoutFail ).toHaveBeenCalledTimes( 1 );

		const failHandler = onCheckoutFail.mock.calls[ 0 ][ 0 ];

		expect(
			failHandler( {
				processingResponse: {
					paymentDetails: { errorMessage: ERROR_MESSAGE },
				},
			} )
		).toEqual( {
			type: 'failure',
			message: 'Error: Amount must be no more than $999,999.99',
			messageContext: 'wc/payment-area',
		} );
	} );
} );
