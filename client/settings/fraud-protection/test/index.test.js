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

jest.mock( '@woocommerce/components', () => ( {
	TourKit: () => <div>Tour Component</div>,
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

		global.wcpaySettings = {
			fraudProtection: {
				isWelcomeTourDismissed: false,
			},
		};
	} );

	it( 'should render correctly', () => {
		const { container: fraudProtectionSettings } = render(
			<WCPaySettingsContext.Provider
				value={ { isWelcomeTourDismissed: false } }
			>
				<FraudProtection />
			</WCPaySettingsContext.Provider>
		);

		expect( fraudProtectionSettings ).toMatchSnapshot();
	} );

	it( 'should render with the welcome tour dismissed', () => {
		global.wcpaySettings.fraudProtection.isWelcomeTourDismissed = true;

		const { container: fraudProtectionSettings } = render(
			<WCPaySettingsContext.Provider
				value={ { isWelcomeTourDismissed: true } }
			>
				<FraudProtection />
			</WCPaySettingsContext.Provider>
		);

		expect( fraudProtectionSettings ).toMatchSnapshot();
	} );
} );
