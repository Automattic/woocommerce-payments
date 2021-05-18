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

describe( 'AdvancedSettings', () => {
	it( 'toggles the advanced settings section', () => {
		render( <AdvancedSettings /> );

		expect( screen.queryByText( 'Debug mode' ) ).not.toBeInTheDocument();

		userEvent.click( screen.getByText( 'Advanced settings' ) );

		expect( screen.queryByText( 'Debug mode' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Debug mode' ) ).toHaveFocus();
	} );

	it( 'focuses on the "custom font" input when the checkbox is checked', () => {
		render( <AdvancedSettings /> );

		userEvent.click( screen.getByText( 'Advanced settings' ) );

		expect( screen.getByText( 'Debug mode' ) ).toHaveFocus();

		expect(
			screen.queryByLabelText( 'Custom font URL' )
		).not.toBeInTheDocument();

		userEvent.click( screen.getByText( 'Use a custom font' ) );
		userEvent.tab();

		expect( screen.getByLabelText( 'Custom font URL' ) ).toHaveFocus();
	} );

	it( 'focuses on the "Additional CSS styling" input when the checkbox is checked', () => {
		render( <AdvancedSettings /> );

		userEvent.click( screen.getByText( 'Advanced settings' ) );

		expect( screen.getByText( 'Debug mode' ) ).toHaveFocus();

		expect(
			screen.queryByLabelText( 'Additional CSS styling' )
		).not.toBeInTheDocument();

		userEvent.click( screen.getByText( 'Add CSS styling' ) );
		userEvent.tab();

		expect(
			screen.getByLabelText( 'Additional CSS styling' )
		).toHaveFocus();
	} );
} );
