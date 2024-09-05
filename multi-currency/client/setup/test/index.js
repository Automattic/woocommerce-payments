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
import { WooPaymentsContextV1 } from 'multi-currency/interface/contexts';

jest.mock( '../tasks/multi-currency-setup', () => jest.fn() );

describe( 'MultiCurrencySetupPage()', () => {
	beforeEach( () => {
		MultiCurrencySetup.mockReturnValue( <p>Multi-Currency setup page</p> );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'renders "Sell worldwide in multiple currencies" page', () => {
		global.wcpaySettings = {
			multiCurrencySetup: {
				isSetupCompleted: true,
			},
		};

		render(
			<WooPaymentsContextV1>
				<MultiCurrencySetupPage />
			</WooPaymentsContextV1>
		);

		expect(
			screen.queryByText( 'Multi-Currency setup page' )
		).toBeInTheDocument();
	} );
} );
