/** @format **/

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import AdvancedSettings from '..';
import { useSettings } from 'wcpay/data';

describe( 'AdvancedSettings', () => {
	it( 'toggles the advanced settings section', () => {
		global.wcpaySettings = {
			isClientEncryptionEligible: true,
		};

		useSettings.mockReturnValue( {
			isLoading: false,
		} );

		render( <AdvancedSettings /> );

		// The advanced settings section is expanded by default.
		expect(
			screen.queryByText( 'Enable Public Key Encryption' )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Debug mode' ) ).toBeInTheDocument();

		// Collapse the advanced settings section.
		userEvent.click( screen.getByText( 'Advanced settings' ) );

		expect( screen.queryByText( 'Debug mode' ) ).not.toBeInTheDocument();
	} );
	it( 'hides the client encryption toggle when not eligible', () => {
		global.wcpaySettings = {
			isClientEncryptionEligible: false,
		};
		render( <AdvancedSettings /> );

		expect( screen.queryByText( 'Debug mode' ) ).not.toBeInTheDocument();

		userEvent.click( screen.getByText( 'Advanced settings' ) );

		expect(
			screen.queryByText( 'Enable Public Key Encryption' )
		).not.toBeInTheDocument();
		expect( screen.queryByText( 'Debug mode' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Debug mode' ) ).toHaveFocus();
	} );
} );
