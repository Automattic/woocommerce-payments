/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen, act } from '@testing-library/react';

/**
 * Internal dependencies
 */
import GeneralSettings from '..';
import {
	useDevMode,
	useIsWCPayEnabled,
	useTestMode,
	useTestModeOnboarding,
} from 'wcpay/data';
import WCPaySettingsContext from 'wcpay/settings/wcpay-settings-context';

jest.mock( 'wcpay/data', () => ( {
	useDevMode: jest.fn(),
	useIsWCPayEnabled: jest.fn(),
	useTestMode: jest.fn(),
	useTestModeOnboarding: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn().mockReturnValue( [ [ 'card' ] ] ),
	useWooPayEnabledSettings: jest.fn().mockReturnValue( [ false ] ),
	usePaymentRequestEnabledSettings: jest.fn().mockReturnValue( [ false ] ),
	useAmazonPayEnabledSettings: jest.fn().mockReturnValue( [ false ] ),
} ) );

const renderWithSettingsProvider = ( ui ) =>
	render(
		<WCPaySettingsContext.Provider
			value={ { featureFlags: { amazonPay: false } } }
		>
			{ ui }
		</WCPaySettingsContext.Provider>
	);

describe( 'GeneralSettings', () => {
	beforeEach( () => {
		useDevMode.mockReturnValue( false );
		useTestModeOnboarding.mockReturnValue( false );
		useIsWCPayEnabled.mockReturnValue( [ false, jest.fn() ] );
		useTestMode.mockReturnValue( [ false, jest.fn() ] );
	} );

	it( 'renders', () => {
		renderWithSettingsProvider( <GeneralSettings /> );

		expect(
			screen.queryByText( 'Enable WooPayments' )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Enable test mode' ) ).toBeInTheDocument();
	} );

	it.each( [ [ true ], [ false ] ] )(
		'displays WCPay enabled = %s state from data store',
		( isEnabled ) => {
			useIsWCPayEnabled.mockReturnValue( [ isEnabled ] );

			renderWithSettingsProvider( <GeneralSettings /> );

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

		renderWithSettingsProvider( <GeneralSettings /> );

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

		renderWithSettingsProvider( <GeneralSettings /> );

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
			renderWithSettingsProvider( <GeneralSettings /> );
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
			renderWithSettingsProvider( <GeneralSettings /> );
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

	it( 'show the modal when the appropriate event is dispatched', () => {
		renderWithSettingsProvider( <GeneralSettings /> );

		act( () => {
			document.dispatchEvent(
				new CustomEvent( 'wcpay:activate_payments' )
			);
		} );

		expect(
			screen.queryByText(
				"Before continuing, please make sure that you're aware of the following:"
			)
		).toBeInTheDocument();
	} );
} );
