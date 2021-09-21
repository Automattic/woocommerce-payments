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
			screen.queryByText( 'Enable WooCommerce Payments' )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Enable test mode' ) ).toBeInTheDocument();
	} );

	it.each( [ [ true ], [ false ] ] )(
		'displays WCPay enabled = %s state from data store',
		( isEnabled ) => {
			useIsWCPayEnabled.mockReturnValue( [ isEnabled ] );

			render( <GeneralSettings /> );

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

			render( <GeneralSettings /> );

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
