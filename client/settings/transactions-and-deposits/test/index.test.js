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
	useIsShortStatementDescriptorEnabled,
	useShortStatementDescriptor,
	useManualCapture,
	useSavedCards,
	useCardPresentEligible,
} from '../../../data';

jest.mock( 'wcpay/data', () => ( {
	useAccountStatementDescriptor: jest.fn(),
	useIsShortStatementDescriptorEnabled: jest.fn(),
	useShortStatementDescriptor: jest.fn(),
	useManualCapture: jest.fn(),
	useGetSavingError: jest.fn(),
	useSavedCards: jest.fn(),
	useCardPresentEligible: jest.fn(),
} ) );

describe( 'TransactionsAndDeposits', () => {
	beforeEach( () => {
		useAccountStatementDescriptor.mockReturnValue( [ '', jest.fn() ] );
		useIsShortStatementDescriptorEnabled.mockReturnValue( [
			false,
			jest.fn(),
		] );
		useShortStatementDescriptor.mockReturnValue( [ '', jest.fn() ] );
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );
		useGetSavingError.mockReturnValue( null );
		useSavedCards.mockReturnValue( [ false, jest.fn() ] );
		useCardPresentEligible.mockReturnValue( [ false ] );
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

		fireEvent.change( screen.getByLabelText( 'Full bank statement' ), {
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

	it( 'toggles the shortened bank statement checkbox', () => {
		const updateIsShortStatementDescriptorEnabled = jest.fn();
		useIsShortStatementDescriptorEnabled.mockReturnValue( [
			false,
			updateIsShortStatementDescriptorEnabled,
		] );

		render( <TransactionsAndDeposits /> );

		fireEvent.click(
			screen.getByLabelText(
				'Add customer order number to the bank statement'
			)
		);

		expect( updateIsShortStatementDescriptorEnabled ).toHaveBeenCalledWith(
			true
		);
	} );

	it( 'does not display shortened bank statement input if it is disabled', () => {
		useIsShortStatementDescriptorEnabled.mockReturnValue( [
			false,
			jest.fn(),
		] );
		expect(
			screen.queryByLabelText( 'Shortened customer bank statement' )
		).not.toBeInTheDocument();
	} );

	it( 'displays the length of the shortened bank statement input', () => {
		const updateShortStatementDescriptor = jest.fn();
		useIsShortStatementDescriptorEnabled.mockReturnValue( [
			true,
			jest.fn(),
		] );
		useShortStatementDescriptor.mockReturnValue( [
			'Statement',
			updateShortStatementDescriptor,
		] );

		render( <TransactionsAndDeposits /> );

		expect( screen.getByText( '9 / 10' ) ).toBeInTheDocument();

		fireEvent.change(
			screen.getByLabelText( 'Shortened customer bank statement' ),
			{
				target: { value: 'New Stmnt' },
			}
		);

		expect( updateShortStatementDescriptor ).toHaveBeenCalledWith(
			'New Stmnt'
		);
	} );

	it( 'displays the error message for the statement input', () => {
		useIsShortStatementDescriptorEnabled.mockReturnValue( [
			true,
			jest.fn(),
		] );
		useShortStatementDescriptor.mockReturnValue( [ '111', jest.fn() ] );
		useGetSavingError.mockReturnValue( {
			code: 'rest_invalid_param',
			message: 'Invalid parameter(s): short_statement_descriptor',
			data: {
				status: 400,
				params: {
					short_statement_descriptor:
						'Shortened customer bank statement is invalid. It should not contain special characters: \' " * &lt; &gt;',
				},
				details: {
					short_statement_descriptor: {
						code: 'rest_invalid_pattern',
						message:
							'Shortened customer bank statement is invalid. It should not contain special characters: \' " * &lt; &gt;',
						data: null,
					},
				},
			},
		} );

		render( <TransactionsAndDeposits /> );

		expect( screen.getByText( '3 / 10' ) ).toBeInTheDocument();
		expect(
			screen.getByText(
				`Shortened customer bank statement is invalid. It should not contain special characters: ' " * < >`
			)
		).toBeInTheDocument();
	} );

	it( 'display ipp payment notice', async () => {
		useCardPresentEligible.mockReturnValue( [ true ] );

		render( <TransactionsAndDeposits /> );

		expect(
			screen.getByText(
				new RegExp( 'The setting is not applied to In-Person Payments' )
			)
		).toBeInTheDocument();
	} );
} );
