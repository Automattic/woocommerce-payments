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
	useEnabledPaymentMethodIds: jest.fn().mockReturnValue( [ [ 'card' ] ] ),
	useWooPayEnabledSettings: jest.fn().mockReturnValue( [ false ] ),
	usePaymentRequestEnabledSettings: jest.fn().mockReturnValue( [ false ] ),
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

	it( 'updates WCPay enabled state to true when toggling checkbox', () => {
		const updateIsWCPayEnabledMock = jest.fn();
		useIsWCPayEnabled.mockReturnValue( [
			false,
			updateIsWCPayEnabledMock,
		] );

		render( <GeneralSettings /> );

		fireEvent.click( screen.getByLabelText( 'Enable WooPayments' ) );

		expect(
			screen.queryByText(
				/WooPayments is currently powering multiple popular payment methods on your store.*/i
			)
		).not.toBeInTheDocument();
		expect( updateIsWCPayEnabledMock ).toHaveBeenCalledWith( true );
	} );

	it( 'shows confirmation modal and disables WooPayments when toggling checkbox', () => {
		const updateIsWCPayEnabledMock = jest.fn();
		useIsWCPayEnabled.mockReturnValue( [ true, updateIsWCPayEnabledMock ] );

		render( <GeneralSettings /> );

		fireEvent.click( screen.getByLabelText( 'Enable WooPayments' ) );

		expect(
			screen.queryByText(
				/WooPayments is currently powering multiple popular payment methods on your store.*/i
			)
		).toBeInTheDocument();
		expect( updateIsWCPayEnabledMock ).not.toHaveBeenCalled();

		fireEvent.click( screen.getByText( 'Disable' ) );

		expect(
			screen.queryByText(
				/WooPayments is currently powering multiple popular payment methods on your store.*/i
			)
		).not.toBeInTheDocument();
		expect( updateIsWCPayEnabledMock ).toHaveBeenCalledWith( false );
	} );

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
