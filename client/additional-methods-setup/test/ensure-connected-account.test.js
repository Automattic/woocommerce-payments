/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import { getHistory, getNewPath } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import EnsureConnectedAccount from '../ensure-connected-account';
import WCPaySettingsContext from 'wcpay/settings/wcpay-settings-context';

jest.mock( '@woocommerce/navigation', () => ( {
	getHistory: jest.fn().mockReturnValue( [] ),
	getNewPath: jest.fn(),
} ) );

describe( 'EnsureConnectedAccount', () => {
	beforeEach( () => {
		global.wcpaySettings = {};
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders its children when the account is connected', () => {
		render(
			<WCPaySettingsContext.Provider value={ { accountStatus: {} } }>
				<EnsureConnectedAccount>
					<span>mocked children</span>
				</EnsureConnectedAccount>
			</WCPaySettingsContext.Provider>
		);

		expect( screen.queryByText( 'mocked children' ) ).toBeInTheDocument();
		expect( getHistory ).not.toHaveBeenCalled();
		expect( getNewPath ).not.toHaveBeenCalled();
	} );

	it( 'redirects to the connect page when the account is not connected', () => {
		render(
			<WCPaySettingsContext.Provider
				value={ { accountStatus: { error: true } } }
			>
				<EnsureConnectedAccount>
					<span>mocked children</span>
				</EnsureConnectedAccount>
			</WCPaySettingsContext.Provider>
		);

		expect(
			screen.queryByText( 'mocked children' )
		).not.toBeInTheDocument();
		expect( getHistory ).toHaveBeenCalled();
		expect( getNewPath ).toHaveBeenCalled();
	} );
} );
