/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import FraudProtection from '..';
import {
	useCurrentProtectionLevel,
	useAdvancedFraudProtectionSettings,
	useSettings,
} from 'wcpay/data';
import WCPaySettingsContext from '../../wcpay-settings-context';

declare const global: {
	wcpaySettings: {
		fraudProtection: {
			isWelcomeTourDismissed: boolean;
		};
	};
};

jest.mock( 'wcpay/data', () => ( {
	useAdvancedFraudProtectionSettings: jest.fn(),
	useCurrentProtectionLevel: jest.fn(),
	useSettings: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
} ) );

jest.mock( '@woocommerce/components', () => ( {
	TourKit: () => <div>Tour Component</div>,
} ) );

const mockUseCurrentProtectionLevel = useCurrentProtectionLevel as jest.MockedFunction<
	() => [ string, ( level: string ) => void ]
>;

const mockUseAdvancedFraudProtectionSettings = useAdvancedFraudProtectionSettings as jest.MockedFunction<
	() => [ any[] | string, ( settings: string ) => void ]
>;

const mockUseSettings = useSettings as jest.MockedFunction<
	() => {
		settings: any;
		isLoading: boolean;
		isDirty: boolean;
		saveSettings: () => void;
		isSaving: boolean;
	}
>;

const mockUseDispatch = useDispatch as jest.MockedFunction< any >;

describe( 'FraudProtection', () => {
	beforeEach( () => {
		mockUseCurrentProtectionLevel.mockReturnValue( [
			'standard',
			jest.fn(),
		] );

		mockUseAdvancedFraudProtectionSettings.mockReturnValue( [
			[],
			jest.fn(),
		] );
		mockUseSettings.mockReturnValue( {
			settings: {},
			isSaving: false,
			isDirty: false,
			saveSettings: jest.fn(),
			isLoading: false,
		} );
		mockUseDispatch.mockReturnValue( { updateOptions: jest.fn() } );

		global.wcpaySettings = {
			fraudProtection: {
				isWelcomeTourDismissed: false,
			},
		};
	} );

	it( 'should render correctly', () => {
		Object.defineProperty( window, 'location', {
			configurable: true,
			enumerable: true,
			value: {
				search:
					'?page=wc-settings&tab=checkout&anchor=%23fp-settings&section=woocommerce_payments/',
			},
		} );

		const { container: fraudProtectionSettings } = render(
			<WCPaySettingsContext.Provider
				value={ { isWelcomeTourDismissed: true } as any }
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
				value={ { isWelcomeTourDismissed: true } as any }
			>
				<FraudProtection />
			</WCPaySettingsContext.Provider>
		);

		expect( fraudProtectionSettings ).toMatchSnapshot();
	} );
} );
