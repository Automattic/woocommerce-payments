/**
 * External dependencies
 */
import { render } from '@testing-library/react';

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
		const { container: fraudProtectionSettings } = render(
			<WCPaySettingsContext.Provider>
				<FraudProtection />
			</WCPaySettingsContext.Provider>
		);

		expect( fraudProtectionSettings ).toMatchSnapshot();
	} );
} );
