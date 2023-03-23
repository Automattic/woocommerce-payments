/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import FraudProtection from '..';
import {
	useCurrentProtectionLevel,
	useCurrencies,
	useAdvancedFraudProtectionSettings,
	useSettings,
} from 'wcpay/data';
import WCPaySettingsContext from '../../wcpay-settings-context';

jest.mock( 'wcpay/data', () => ( {
	useAdvancedFraudProtectionSettings: jest.fn(),
	useCurrentProtectionLevel: jest.fn(),
	useSettings: jest.fn(),
	useCurrencies: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
} ) );

describe( 'FraudProtection', () => {
	beforeEach( () => {
		useCurrentProtectionLevel.mockReturnValue( 'standard' );
		useCurrencies.mockReturnValue( {
			isLoading: false,
			currencies: {
				available: {
					EUR: { name: 'Euro', symbol: '€' },
					USD: { name: 'US Dollar', symbol: '$' },
					PLN: { name: 'Polish złoty', symbol: 'zł' },
				},
			},
		} );

		useAdvancedFraudProtectionSettings.mockReturnValue( [ [], jest.fn() ] );
		useSettings.mockReturnValue( { isLoading: false } );
		useDispatch.mockReturnValue( { updateOptions: jest.fn() } );

		window.scrollTo = jest.fn();

		global.wcpaySettings = {
			fraudProtection: {
				isWelcomeTourDismissed: false,
			},
		};
	} );

	it( 'renders', () => {
		const { container: fraudProtectionSettings } = render(
			<WCPaySettingsContext.Provider>
				<FraudProtection />
			</WCPaySettingsContext.Provider>
		);

		expect( fraudProtectionSettings ).toMatchSnapshot();
	} );
} );
