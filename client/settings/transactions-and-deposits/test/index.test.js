/**
 * External dependencies
 */
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { useAccountStatementDescriptor, useManualCapture } from 'data';
import TransactionsAndDeposits from '..';
import WCPaySettingsContext from '../../wcpay-settings-context';

jest.mock( 'data', () => ( {
	useAccountStatementDescriptor: jest.fn(),
	useManualCapture: jest.fn(),
} ) );

describe( 'TransactionsAndDeposits', () => {
	beforeEach( () => {
		useAccountStatementDescriptor.mockReturnValue( [ '', jest.fn() ] );
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );
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

		const manageLink = screen.getByText( '14 / 22' );
		expect( manageLink ).toBeInTheDocument();

		fireEvent.change( screen.getByLabelText( 'Customer bank statement' ), {
			target: { value: 'New Statement Name' },
		} );

		expect( updateAccountStatementDescriptor ).toHaveBeenCalledWith(
			'New Statement Name'
		);
	} );
} );
