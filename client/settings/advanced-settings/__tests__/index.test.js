/** @format **/

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AdvancedSettings from '..';
import {
	useMultiCurrency,
	useWCPaySubscriptions,
	useDevMode,
	useDebugLog,
} from 'wcpay/data';

jest.mock( '../../../data', () => ( {
	useSettings: jest.fn(),
	useMultiCurrency: jest.fn(),
	useWCPaySubscriptions: jest.fn(),
	useDevMode: jest.fn(),
	useDebugLog: jest.fn(),
} ) );

describe( 'AdvancedSettings', () => {
	beforeEach( () => {
		useMultiCurrency.mockReturnValue( [ false, jest.fn() ] );
		useWCPaySubscriptions.mockReturnValue( [ false, jest.fn() ] );
		useDevMode.mockReturnValue( false );
		useDebugLog.mockReturnValue( [ false, jest.fn() ] );
	} );
	test( 'toggles the advanced settings section', () => {
		render( <AdvancedSettings /> );
		// The advanced settings section is expanded by default.
		expect( screen.queryByText( 'Debug mode' ) ).toBeInTheDocument();
	} );
} );
