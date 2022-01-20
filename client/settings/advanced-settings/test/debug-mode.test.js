/** @format **/

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { useDebugLog, useDevMode } from 'wcpay/data';
import DebugMode from '../debug-mode';

jest.mock( '../../../data', () => ( {
	useDevMode: jest.fn().mockReturnValue( false ),
	useDebugLog: jest.fn().mockReturnValue( [ false, jest.fn() ] ),
} ) );

describe( 'DebugMode', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'sets the heading as focused after rendering', () => {
		render( <DebugMode /> );

		expect( screen.getByText( 'Debug mode' ) ).toHaveFocus();
	} );

	it( 'toggles the logging checkbox', () => {
		const setDebugLogMock = jest.fn();
		useDebugLog.mockReturnValue( [ false, setDebugLogMock ] );

		render( <DebugMode /> );

		const loggingCheckbox = screen.queryByRole( 'checkbox', {
			name: 'Log error messages',
		} );

		expect(
			screen.queryByText(
				'Dev mode is active so logging is on by default.'
			)
		).not.toBeInTheDocument();
		expect( loggingCheckbox ).not.toBeChecked();
		expect( setDebugLogMock ).not.toHaveBeenCalled();

		userEvent.click( loggingCheckbox );

		expect( setDebugLogMock ).toHaveBeenCalledWith( true );
	} );

	it( 'prevents toggling the logging checkbox when dev mode is active', () => {
		useDevMode.mockReturnValue( true );

		render( <DebugMode /> );

		const loggingCheckbox = screen.queryByRole( 'checkbox', {
			name: 'Dev mode is active so logging is on by default.',
		} );

		expect(
			screen.queryByText( 'Log error messages' )
		).not.toBeInTheDocument();
		expect( loggingCheckbox ).toBeChecked();
		expect( loggingCheckbox ).toBeDisabled();
	} );
} );
