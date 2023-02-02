/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import FraudProtection from '..';
import { useCurrentProtectionLevel, useCurrencies } from 'wcpay/data';
import WCPaySettingsContext from '../../wcpay-settings-context';

jest.mock( 'wcpay/data', () => ( {
	useCurrentProtectionLevel: jest.fn(),
	useCurrencies: jest.fn(),
} ) );

describe( 'FraudProtection', () => {
	beforeEach(
		() => useCurrentProtectionLevel.mockReturnValue( 'standard' ),
		useCurrencies.mockReturnValue( {
			isLoading: false,
			currencies: {
				available: {
					EUR: { name: 'Euro', symbol: '€' },
					USD: { name: 'US Dollar', symbol: '$' },
					PLN: { name: 'Polish złoty', symbol: 'zł' },
				},
			},
		} )
	);

	it( 'renders', () => {
		render(
			<WCPaySettingsContext.Provider>
				<FraudProtection />
			</WCPaySettingsContext.Provider>
		);

		const highHelp = screen.getByText( /High protection:/ );
		expect( highHelp ).toBeInTheDocument();
		expect( highHelp ).toHaveTextContent(
			'High protection: Offers the highest level of filtering for stores, but may catch some legitimate transactions.'
		);
	} );
} );
