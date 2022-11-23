/**
 * External dependencies
 */
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Transactions from '..';
import {
	useGetSavingError,
	useAccountStatementDescriptor,
	useManualCapture,
	useSavedCards,
	useCardPresentEligible,
} from '../../../data';

jest.mock( 'wcpay/data', () => ( {
	useAccountStatementDescriptor: jest.fn(),
	useManualCapture: jest.fn(),
	useGetSavingError: jest.fn(),
	useSavedCards: jest.fn(),
	useCardPresentEligible: jest.fn(),
} ) );

describe( 'TransactionsAndDeposits', () => {
	beforeEach( () => {
		useAccountStatementDescriptor.mockReturnValue( [ '', jest.fn() ] );
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );
		useGetSavingError.mockReturnValue( null );
		useSavedCards.mockReturnValue( [ false, jest.fn() ] );
		useCardPresentEligible.mockReturnValue( [ false ] );
	} );

	it( 'displays the length of the bank statement input', async () => {
		const updateAccountStatementDescriptor = jest.fn();
		useAccountStatementDescriptor.mockReturnValue( [
			'Statement Name',
			updateAccountStatementDescriptor,
		] );

		render( <Transactions /> );

		expect( screen.getByText( '14 / 22' ) ).toBeInTheDocument();

		fireEvent.change( screen.getByLabelText( 'Customer bank statement' ), {
			target: { value: 'New Statement Name' },
		} );

		expect( updateAccountStatementDescriptor ).toHaveBeenCalledWith(
			'New Statement Name'
		);
	} );

	it( 'displays the error message for the statement input', async () => {
		useAccountStatementDescriptor.mockReturnValue( [ '111', jest.fn() ] );
		useGetSavingError.mockReturnValue( {
			code: 'rest_invalid_param',
			message: 'Invalid parameter(s): account_statement_descriptor',
			data: {
				status: 400,
				params: {
					account_statement_descriptor:
						'Customer bank statement is invalid. It should not contain special characters: \' " * &lt; &gt;',
				},
				details: {
					account_statement_descriptor: {
						code: 'rest_invalid_pattern',
						message:
							'Customer bank statement is invalid. It should not contain special characters: \' " * &lt; &gt;',
						data: null,
					},
				},
			},
		} );

		render( <Transactions /> );

		expect( screen.getByText( '3 / 22' ) ).toBeInTheDocument();
		expect(
			screen.getByText(
				`Customer bank statement is invalid. It should not contain special characters: ' " * < >`
			)
		).toBeInTheDocument();
	} );

	it( 'display ipp payment notice', async () => {
		useCardPresentEligible.mockReturnValue( [ true ] );

		render( <Transactions /> );

		expect(
			screen.getByText(
				new RegExp( 'The setting is not applied to In-Person Payments' )
			)
		).toBeInTheDocument();
	} );
} );
