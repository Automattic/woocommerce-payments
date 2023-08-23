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
	useAccountBusinessSupportEmail,
	useAccountBusinessSupportPhone,
	useManualCapture,
	useSavedCards,
	useCardPresentEligible,
} from '../../../data';

jest.mock( 'wcpay/data', () => ( {
	useAccountStatementDescriptor: jest.fn(),
	useAccountBusinessSupportEmail: jest.fn(),
	useAccountBusinessSupportPhone: jest.fn(),
	useManualCapture: jest.fn(),
	useGetSavingError: jest.fn(),
	useSavedCards: jest.fn(),
	useCardPresentEligible: jest.fn(),
} ) );

describe( 'Settings - Transactions', () => {
	beforeEach( () => {
		useAccountStatementDescriptor.mockReturnValue( [ '', jest.fn() ] );
		useAccountBusinessSupportEmail.mockReturnValue( [
			'test@test.com',
			jest.fn(),
		] );
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+12345678901',
			jest.fn(),
		] );
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );
		useGetSavingError.mockReturnValue( null );
		useSavedCards.mockReturnValue( [ false, jest.fn() ] );
		useCardPresentEligible.mockReturnValue( [ false ] );
		window.wcpaySettings = {
			accountStatus: {
				country: 'US',
			},
		};
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

	it( 'display support email and phone inputs', async () => {
		render( <Transactions /> );
		expect(
			screen.getByLabelText( 'Support phone number' )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Support email' ) ).toBeInTheDocument();
	} );
} );
