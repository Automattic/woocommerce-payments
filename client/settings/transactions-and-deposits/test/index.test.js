/**
 * External dependencies
 */
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import TransactionsAndDeposits from '..';
import WCPaySettingsContext from '../../wcpay-settings-context';
import {
	useGetSavingError,
	useAccountStatementDescriptor,
	useManualCapture,
	useSavedCards,
} from '../../../data';

jest.mock( 'wcpay/data', () => ( {
	useAccountStatementDescriptor: jest.fn(),
	useManualCapture: jest.fn(),
	useGetSavingError: jest.fn(),
	useSavedCards: jest.fn(),
} ) );

describe( 'TransactionsAndDeposits', () => {
	beforeEach( () => {
		useAccountStatementDescriptor.mockReturnValue( [ '', jest.fn() ] );
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );
		useGetSavingError.mockReturnValue( null );
		useSavedCards.mockReturnValue( [ false, jest.fn() ] );
	} );

	it( 'renders', () => {
		const settingsContext = {
			accountStatus: { accountLink: '/account-link' },
		};

		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<TransactionsAndDeposits />
			</WCPaySettingsContext.Provider>
		);

		const manageLink = screen.queryByText( 'Manage in Stripe' );
		expect( manageLink ).toBeInTheDocument();
		expect( manageLink ).toHaveTextContent(
			'Manage in Stripe(opens in a new tab)'
		);
		expect( manageLink.href ).toContain( '/account-link' );
	} );

	it( 'displays the length of the bank statement input', async () => {
		const updateAccountStatementDescriptor = jest.fn();
		useAccountStatementDescriptor.mockReturnValue( [
			'Statement Name',
			updateAccountStatementDescriptor,
		] );

		render( <TransactionsAndDeposits /> );

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

		render( <TransactionsAndDeposits /> );

		expect( screen.getByText( '3 / 22' ) ).toBeInTheDocument();
		expect(
			screen.getByText(
				`Customer bank statement is invalid. It should not contain special characters: ' " * < >`
			)
		).toBeInTheDocument();
	} );
} );
