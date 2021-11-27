/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import MultiCurrencySetup from '../tasks/multi-currency-setup';

/**
 * Internal dependencies
 */
import MultiCurrencySetupPage from '..';

jest.mock( '../tasks/multi-currency-setup', () => jest.fn() );

describe( 'MultiCurrencySetupPage()', () => {
	beforeEach( () => {
		MultiCurrencySetup.mockReturnValue( <p>Multi-Currency setup task</p> );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'renders "Sell worldwide in multiple currencies" task', () => {
		global.wcpaySettings = {
			multiCurrencySetup: {
				isSetupCompleted: true,
			},
		};

		render( <MultiCurrencySetupPage /> );

		expect(
			screen.queryByText( 'Multi-Currency setup task' )
		).toBeInTheDocument();
	} );
} );
