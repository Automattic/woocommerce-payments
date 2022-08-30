/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Deposits from '..';
import WCPaySettingsContext from '../../wcpay-settings-context';

jest.mock( 'wcpay/data', () => ( {
	useAccountStatementDescriptor: jest.fn(),
	useManualCapture: jest.fn(),
	useGetSavingError: jest.fn(),
	useSavedCards: jest.fn(),
	useCardPresentEligible: jest.fn(),
} ) );

describe( 'Deposits', () => {
	beforeEach( () => {} );

	it( 'renders', () => {
		const settingsContext = {
			accountStatus: { accountLink: '/account-link' },
		};

		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<Deposits />
			</WCPaySettingsContext.Provider>
		);

		const manageLink = screen.queryByText( 'Manage in Stripe' );
		expect( manageLink ).toBeInTheDocument();
		expect( manageLink ).toHaveTextContent(
			'Manage in Stripe(opens in a new tab)'
		);
		expect( manageLink.href ).toContain( '/account-link' );
	} );
} );
