/**
 * External dependencies
 */
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import GeneralSettings from '..';
import { useDevMode, useIsWCPayEnabled, useTestMode } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useDevMode: jest.fn(),
	useIsWCPayEnabled: jest.fn(),
	useTestMode: jest.fn(),
} ) );

describe( 'GeneralSettings', () => {
	beforeEach( () => {
		useDevMode.mockReturnValue( false );
		useIsWCPayEnabled.mockReturnValue( [ false, jest.fn() ] );
		useTestMode.mockReturnValue( [ false, jest.fn() ] );
	} );

	it( 'renders', () => {
		render( <GeneralSettings /> );

		expect(
			screen.queryByText( 'Enable WooPayments' )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Enable test mode' ) ).toBeInTheDocument();
	} );

	it.each( [ [ true ], [ false ] ] )(
		'displays WCPay enabled = %s state from data store',
		( isEnabled ) => {
			useIsWCPayEnabled.mockReturnValue( [ isEnabled ] );

			render( <GeneralSettings /> );

			const enableWCPayCheckbox = screen.getByLabelText(
				'Enable WooPayments'
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

			render( <GeneralSettings /> );

			const enableWCPayCheckbox = screen.getByLabelText(
				'Enable WooPayments'
			);

			fireEvent.click( enableWCPayCheckbox );
			expect( updateIsWCPayEnabledMock ).toHaveBeenCalledWith(
				! isEnabled
			);
		}
	);

	it.each( [ [ true ], [ false ] ] )(
		'display of CheckBox when initial Test Mode = %s',
		( isEnabled ) => {
			useTestMode.mockReturnValue( [ isEnabled, jest.fn() ] );
			render( <GeneralSettings /> );
			const enableTestModeCheckbox = screen.getByLabelText(
				'Enable test mode'
			);

			let expectation = expect( enableTestModeCheckbox );
			if ( ! isEnabled ) {
				expectation = expectation.not;
			}
			expectation.toBeChecked();
		}
	);

	it.each( [ [ true ], [ false ] ] )(
		'Checks Confirmation Modal display when initial Test Mode = %s',
		( isEnabled ) => {
			useTestMode.mockReturnValue( [ isEnabled, jest.fn() ] );
			render( <GeneralSettings /> );
			const enableTestModeCheckbox = screen.getByLabelText(
				'Enable test mode'
			);
			fireEvent.click( enableTestModeCheckbox );

			let expectation = expect(
				screen.queryByText(
					'Are you sure you want to enable test mode?'
				)
			);
			if ( isEnabled ) {
				expectation = expectation.not;
			}
			expectation.toBeInTheDocument();
		}
	);
} );
