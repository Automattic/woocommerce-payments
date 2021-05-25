/**
 * External dependencies
 */
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import GeneralSettings from '..';
import {
	useAccountStatementDescriptor,
	useManualCapture,
	useIsWCPayEnabled,
} from 'data';

jest.mock( 'data', () => ( {
	useAccountStatementDescriptor: jest.fn(),
	useManualCapture: jest.fn(),
	useIsWCPayEnabled: jest.fn(),
} ) );

describe( 'GeneralSettings', () => {
	beforeEach( () => {
		useAccountStatementDescriptor.mockReturnValue( [ '', jest.fn() ] );
		useIsWCPayEnabled.mockReturnValue( [ false, jest.fn() ] );
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );
	} );

	it( 'renders', () => {
		render( <GeneralSettings accountLink="/account-link" /> );

		const manageLink = screen.queryByText( 'Manage in Stripe' );
		expect( manageLink ).toBeInTheDocument();
		expect( manageLink ).toHaveTextContent(
			'Manage in Stripe(opens in a new tab)'
		);
		expect( manageLink.href ).toContain( '/account-link' );

		expect(
			screen.queryByText( 'Enable WooCommerce Payments' )
		).toBeInTheDocument();
	} );

	it( 'displays the length of the bank statement input', async () => {
		const updateAccountStatementDescriptor = jest.fn();
		useAccountStatementDescriptor.mockReturnValue( [
			'Statement Name',
			updateAccountStatementDescriptor,
		] );

		render( <GeneralSettings accountLink="/account-link" /> );

		const manageLink = screen.getByText( '14 / 22' );
		expect( manageLink ).toBeInTheDocument();

		fireEvent.change( screen.getByLabelText( 'Customer bank statement' ), {
			target: { value: 'New Statement Name' },
		} );

		expect( updateAccountStatementDescriptor ).toHaveBeenCalledWith(
			'New Statement Name'
		);
	} );

	it.each( [ [ true ], [ false ] ] )(
		'displays WCPay enabled = %s state from data store',
		( isEnabled ) => {
			useIsWCPayEnabled.mockReturnValue( [ isEnabled ] );

			render( <GeneralSettings accountLink="/account-link" /> );

			const enableWCPayCheckbox = screen.getByLabelText(
				'Enable WooCommerce Payments'
			);

			let expectation = expect( enableWCPayCheckbox );
			if ( ! isEnabled ) {
				expectation = expectation.not;
			}
			expectation.toBeChecked();
		}
	);

	it.each( [ [ true ], [ false ] ] )(
		'updates WCPay enabled state to %s when toggling checkbox',
		( isEnabled ) => {
			const updateIsWCPayEnabledMock = jest.fn();
			useIsWCPayEnabled.mockReturnValue( [
				isEnabled,
				updateIsWCPayEnabledMock,
			] );

			render( <GeneralSettings accountLink="/account-link" /> );

			const enableWCPayCheckbox = screen.getByLabelText(
				'Enable WooCommerce Payments'
			);

			fireEvent.click( enableWCPayCheckbox );
			expect( updateIsWCPayEnabledMock ).toHaveBeenCalledWith(
				! isEnabled
			);
		}
	);
} );
