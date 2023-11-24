/**
 * External dependencies
 */
import React from 'react';
import { render, waitFor } from '@testing-library/react';
import { mocked } from 'ts-jest/utils';

/**
 * Internal dependencies
 */
import FraudProtectionAdvancedSettingsPage from '..';
import {
	useAdvancedFraudProtectionSettings,
	useCurrentProtectionLevel,
	useSettings,
} from '../../../../data';

jest.mock( '../../../../data', () => ( {
	useSettings: jest.fn(),
	useCurrentProtectionLevel: jest.fn(),
	useAdvancedFraudProtectionSettings: jest.fn(),
} ) );

// Workaround for mocking @wordpress/data.
// See https://github.com/WordPress/gutenberg/issues/15031
jest.mock( '@wordpress/data', () => ( {
	createRegistryControl: jest.fn(),
	dispatch: jest.fn( () => ( {
		setIsMatching: jest.fn(),
		createSuccessNotice: jest.fn(),
		onLoad: jest.fn(),
	} ) ),
	registerStore: jest.fn(),
	select: jest.fn(),
	useDispatch: jest.fn( () => ( { createNotice: jest.fn() } ) ),
	withDispatch: jest.fn( () => jest.fn() ),
	withSelect: jest.fn( () => jest.fn() ),
} ) );

let defaultSettings: any[] = [];
let container: any = null;

declare const global: {
	wcSettings: {
		admin: {
			preloadSettings: {
				general: {
					woocommerce_allowed_countries: string;
					woocommerce_all_except_countries: string[];
					woocommerce_specific_allowed_countries: string[];
				};
			};
		};
	};
	wcpaySettings: {
		storeCurrency: string;
		connect: {
			country: string;
		};
		currencyData: Record<
			string,
			{
				code: string;
				symbol: string;
				symbolPosition: string;
				thousandSeparator: string;
				decimalSeparator: string;
				precision: number;
			}
		>;
		isMultiCurrencyEnabled: string;
	};
};

const mockUseCurrentProtectionLevel = mocked( useCurrentProtectionLevel );

const mockUseAdvancedFraudProtectionSettings = mocked(
	useAdvancedFraudProtectionSettings
);

const mockUseSettings = useSettings as jest.MockedFunction<
	() => {
		settings: any;
		isLoading: boolean;
		saveSettings: jest.Mock;
		isSaving: boolean;
	}
>;

describe( 'Advanced fraud protection settings', () => {
	beforeEach( () => {
		window.scrollTo = jest.fn();
		const protectionSettings = {
			state: [],
			updateState: jest.fn( ( settings ) => {
				protectionSettings.state = settings;
			} ),
		};
		const protectionLevelState = {
			state: 'standard',
			updateState: jest.fn( ( level ) => {
				protectionLevelState.state = level;
			} ),
		};
		mockUseCurrentProtectionLevel.mockReturnValue( [
			protectionLevelState.state,
			protectionLevelState.updateState,
		] );
		global.wcSettings = {
			admin: {
				preloadSettings: {
					general: {
						woocommerce_allowed_countries: 'all',
						woocommerce_all_except_countries: [],
						woocommerce_specific_allowed_countries: [],
					},
				},
			},
		};

		global.wcpaySettings = {
			storeCurrency: 'USD',
			connect: {
				country: 'US',
			},
			currencyData: {
				US: {
					code: 'USD',
					symbol: '$',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
			},
			isMultiCurrencyEnabled: '1',
		};

		mockUseAdvancedFraudProtectionSettings.mockReturnValue( [
			protectionSettings.state,
			protectionSettings.updateState,
		] );
		const mockIntersectionObserver = jest.fn();
		mockIntersectionObserver.mockReturnValue( {
			observe: () => null,
			unobserve: () => null,
			disconnect: () => null,
		} );
		window.IntersectionObserver = mockIntersectionObserver;

		container = null;
	} );
	afterEach( () => {
		jest.clearAllMocks();
		container?.unmount();
		defaultSettings = [];
	} );
	test( 'renders correctly', () => {
		mockUseSettings.mockReturnValue( {
			settings: {
				advanced_fraud_protection_settings: [],
			},
			saveSettings: jest.fn(),
			isSaving: false,
			isLoading: false,
		} );
		mockUseAdvancedFraudProtectionSettings.mockReturnValue( [
			[],
			jest.fn(),
		] );
		container = render( <FraudProtectionAdvancedSettingsPage /> );
		expect( container ).toMatchSnapshot();
	} );
	it( 'renders an error message when settings can not be fetched from the server', async () => {
		mockUseSettings.mockReturnValue( {
			settings: {
				advanced_fraud_protection_settings: 'error',
			},
			saveSettings: jest.fn(),
			isSaving: false,
			isLoading: false,
		} );
		mockUseAdvancedFraudProtectionSettings.mockReturnValue( [
			'error',
			jest.fn(),
		] );
		container = render(
			<div>
				<div className="woocommerce-layout__header-wrapper">
					<div className="woocommerce-layout__header-heading"></div>
				</div>
				<FraudProtectionAdvancedSettingsPage />
			</div>
		);

		expect( container ).toMatchSnapshot();
		expect( container.baseElement ).toHaveTextContent(
			/There was an error retrieving your fraud protection settings/i
		);

		const [
			firstSaveButton,
			secondSaveButton,
		] = await container.findAllByText( 'Save Changes' );

		expect( firstSaveButton ).toBeDisabled();
		expect( secondSaveButton ).toBeDisabled();
	} );
	test( "doesn't save when there's a validation error", async () => {
		defaultSettings.push( {
			key: 'purchase_price_threshold',
			outcome: 'block',
			check: {
				operator: 'or',
				checks: [
					{ key: 'order_total', operator: 'less_than', value: 1000 },
					{
						key: 'order_total',
						operator: 'greater_than',
						value: 100,
					},
				],
			},
		} );
		mockUseAdvancedFraudProtectionSettings.mockReturnValue( [
			defaultSettings,
			jest.fn(),
		] );

		mockUseSettings.mockReturnValue( {
			settings: {
				advanced_fraud_protection_settings: defaultSettings,
			},
			saveSettings: jest.fn(),
			isLoading: false,
			isSaving: false,
		} );

		container = render(
			<div>
				<div className="woocommerce-layout__header-wrapper">
					<div className="woocommerce-layout__header-heading"></div>
				</div>
				<FraudProtectionAdvancedSettingsPage />
			</div>
		);

		const [ saveButton ] = await container.findAllByText( 'Save Changes' );
		saveButton.click();
		expect( mockUseSettings().saveSettings.mock.calls.length ).toBe( 0 );
		expect( container ).toMatchSnapshot();
		expect(
			document.querySelectorAll(
				'.fraud-protection-advanced-settings-error-notice'
			).length
		).toBe( 1 );
	} );
	test( 'saves settings when there are no validation errors', async () => {
		defaultSettings.push( {
			key: 'purchase_price_threshold',
			outcome: 'block',
			check: {
				operator: 'or',
				checks: [
					{ key: 'order_total', operator: 'less_than', value: 100 },
					{
						key: 'order_total',
						operator: 'greater_than',
						value: 1000,
					},
				],
			},
		} );
		mockUseSettings.mockReturnValue( {
			settings: {
				advanced_fraud_protection_settings: defaultSettings,
			},
			saveSettings: jest.fn(),
			isSaving: false,
			isLoading: false,
		} );
		mockUseAdvancedFraudProtectionSettings.mockReturnValue( [
			defaultSettings,
			jest.fn(),
		] );
		container = render(
			<div>
				<div className="woocommerce-layout__header-wrapper">
					<div className="woocommerce-layout__header-heading"></div>
				</div>
				<FraudProtectionAdvancedSettingsPage />
			</div>
		);
		const [ saveButton ] = await container.findAllByText( 'Save Changes' );
		saveButton.click();
		await waitFor( () => {
			expect( mockUseSettings().saveSettings.mock.calls.length ).toBe(
				1
			);
		} );
		expect( container ).toMatchSnapshot();
		expect(
			document.querySelectorAll(
				'fraud-protection-advanced-settings-error-notice'
			).length
		).toBe( 0 );
	} );
	test( 'updates protection level to advanced when its not at advanced level', async () => {
		const protectionLevelState = {
			state: 'standard',
			updateState: jest.fn( ( level ) => {
				protectionLevelState.state = level;
			} ),
		};
		mockUseCurrentProtectionLevel.mockReturnValue( [
			protectionLevelState.state,
			protectionLevelState.updateState,
		] );
		defaultSettings.push( {
			key: 'purchase_price_threshold',
			outcome: 'block',
			check: {
				operator: 'or',
				checks: [
					{ key: 'order_total', operator: 'less_than', value: 100 },
					{
						key: 'order_total',
						operator: 'greater_than',
						value: 1000,
					},
				],
			},
		} );
		mockUseSettings.mockReturnValue( {
			settings: {
				advanced_fraud_protection_settings: defaultSettings,
			},
			isSaving: false,
			saveSettings: jest.fn(),
			isLoading: false,
		} );
		mockUseAdvancedFraudProtectionSettings.mockReturnValue( [
			defaultSettings,
			jest.fn(),
		] );
		container = render(
			<div>
				<div className="woocommerce-layout__header-wrapper">
					<div className="woocommerce-layout__header-heading"></div>
				</div>
				<FraudProtectionAdvancedSettingsPage />
			</div>
		);
		const [ saveButton ] = await container.findAllByText( 'Save Changes' );
		saveButton.click();
		await waitFor( () => {
			expect( mockUseSettings().saveSettings.mock.calls.length ).toBe(
				1
			);
		} );
		expect(
			document.querySelectorAll(
				'fraud-protection-advanced-settings-error-notice'
			).length
		).toBe( 0 );
		expect( protectionLevelState.state ).toBe( 'advanced' );
		expect( protectionLevelState.updateState.mock.calls.length ).toBe( 1 );
		expect( protectionLevelState.updateState.mock.calls ).toEqual( [
			[ 'advanced' ],
		] );
	} );
	test( 'doesnt update protection level to advanced when its already at advanced level', async () => {
		const protectionLevelState = {
			state: 'advanced',
			updateState: jest.fn( ( level ) => {
				protectionLevelState.state = level;
			} ),
		};
		mockUseCurrentProtectionLevel.mockReturnValue( [
			protectionLevelState.state,
			protectionLevelState.updateState,
		] );
		defaultSettings.push( {
			key: 'purchase_price_threshold',
			outcome: 'block',
			check: {
				operator: 'or',
				checks: [
					{ key: 'order_total', operator: 'less_than', value: 100 },
					{
						key: 'order_total',
						operator: 'greater_than',
						value: 1000,
					},
				],
			},
		} );
		mockUseSettings.mockReturnValue( {
			settings: {
				advanced_fraud_protection_settings: defaultSettings,
			},
			saveSettings: jest.fn(),
			isSaving: false,
			isLoading: false,
		} );
		mockUseAdvancedFraudProtectionSettings.mockReturnValue( [
			defaultSettings,
			jest.fn(),
		] );
		container = render(
			<div>
				<div className="woocommerce-layout__header-wrapper">
					<div className="woocommerce-layout__header-heading"></div>
				</div>
				<FraudProtectionAdvancedSettingsPage />
			</div>
		);
		const [ saveButton ] = await container.findAllByText( 'Save Changes' );
		saveButton.click();
		await waitFor( () => {
			expect( mockUseSettings().saveSettings.mock.calls.length ).toBe(
				1
			);
		} );
		expect(
			document.querySelectorAll(
				'fraud-protection-advanced-settings-error-notice'
			).length
		).toBe( 0 );
		expect( protectionLevelState.state ).toBe( 'advanced' );
		expect( protectionLevelState.updateState.mock.calls.length ).toBe( 0 );
		expect( protectionLevelState.updateState.mock.calls ).toEqual( [] );
	} );
} );
