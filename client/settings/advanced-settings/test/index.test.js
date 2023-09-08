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
	useClientSecretEncryption,
} from 'wcpay/data';

jest.mock( '../../../data', () => ( {
	useSettings: jest.fn(),
	useMultiCurrency: jest.fn(),
	useWCPaySubscriptions: jest.fn(),
	useDevMode: jest.fn(),
	useDebugLog: jest.fn(),
	useClientSecretEncryption: jest.fn(),
} ) );

describe( 'AdvancedSettings', () => {
	beforeEach( () => {
		useMultiCurrency.mockReturnValue( [ false, jest.fn() ] );
		useWCPaySubscriptions.mockReturnValue( [ false, jest.fn() ] );
		useDevMode.mockReturnValue( false );
		useDebugLog.mockReturnValue( [ false, jest.fn() ] );
		useClientSecretEncryption.mockReturnValue( [ false, jest.fn() ] );
	} );
	test( 'toggles the advanced settings section', () => {
		global.wcpaySettings = {
			isClientEncryptionEligible: true,
		};

		render( <AdvancedSettings /> );

		// The advanced settings section is expanded by default.
		expect(
			screen.queryByText( 'Enable Public Key Encryption' )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Debug mode' ) ).toBeInTheDocument();
	} );
	test( 'hides the client encryption toggle when not eligible', () => {
		global.wcpaySettings = {
			isClientEncryptionEligible: false,
		};

		render( <AdvancedSettings /> );

		expect(
			screen.queryByText( 'Enable Public Key Encryption' )
		).not.toBeInTheDocument();
		expect( screen.queryByText( 'Debug mode' ) ).toBeInTheDocument();
	} );
} );
