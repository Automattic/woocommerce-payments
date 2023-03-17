/**
 * External dependencies
 */
import React from 'react';
import { render, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import FraudProtectionAdvancedSettingsPage from '..';
import { useCurrentProtectionLevel, useSettings } from '../../../../data';

jest.mock( '../../../../data', () => ( {
	useSettings: jest.fn(),
	useCurrentProtectionLevel: jest.fn(),
} ) );

// Workaround for mocking @wordpress/data.
// See https://github.com/WordPress/gutenberg/issues/15031
jest.mock( '@wordpress/data', () => ( {
	createRegistryControl: jest.fn(),
	dispatch: jest.fn( () => ( {
		setIsMatching: jest.fn(),
		createSuccessNotice: jest.fn(),
	} ) ),
	registerStore: jest.fn(),
	select: jest.fn(),
	useDispatch: jest.fn( () => ( { createNotice: jest.fn() } ) ),
	withDispatch: jest.fn( () => jest.fn() ),
	withSelect: jest.fn( () => jest.fn() ),
} ) );

let defaultSettings = [];

describe( 'Advanced fraud protection settings', () => {
	beforeEach( () => {
		window.scrollTo = jest.fn();
		const protectionLevelState = {
			state: 'standard',
			updateState: jest.fn( ( level ) => {
				protectionLevelState.state = level;
			} ),
		};
		useCurrentProtectionLevel.mockReturnValue( [
			protectionLevelState.state,
			protectionLevelState.updateState,
		] );
	} );
	afterEach( () => {
		jest.clearAllMocks();
		defaultSettings = [];
	} );
	test( 'renders correctly', () => {
		useSettings.mockReturnValue( {
			settings: {
				advanced_fraud_protection_settings: [],
			},
			saveSettings: jest.fn(),
			isLoading: false,
		} );
		const container = render( <FraudProtectionAdvancedSettingsPage /> );
		expect( container ).toMatchSnapshot();
	} );
	test( "doesn't save when there's validation errors", async () => {
		defaultSettings.push( {
			key: 'purchase_price_threshold',
			outcome: 'block',
			check: {
				operator: 'or',
				checks: [
					{ key: 'order_total', operator: 'less_than', value: 10 },
					{ key: 'order_total', operator: 'greater_than', value: 1 },
				],
			},
		} );
		useSettings.mockReturnValue( {
			settings: {
				advanced_fraud_protection_settings: defaultSettings,
			},
			saveSettings: jest.fn(),
			isLoading: false,
		} );
		const settingsMock = useSettings();
		const container = render(
			<div>
				<div className="woocommerce-layout__header-wrapper">
					<div className="woocommerce-layout__header-heading"></div>
				</div>
				<FraudProtectionAdvancedSettingsPage />
			</div>
		);
		const saveButton = await container.findByText( 'Save Changes' );
		saveButton.click();
		await waitFor( () => {
			expect( settingsMock.saveSettings.mock.calls.length ).toBe( 0 );
		} );
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
					{ key: 'order_total', operator: 'less_than', value: 1 },
					{ key: 'order_total', operator: 'greater_than', value: 10 },
				],
			},
		} );
		useSettings.mockReturnValue( {
			settings: {
				advanced_fraud_protection_settings: defaultSettings,
			},
			saveSettings: jest.fn(),
			isLoading: false,
		} );
		const settingsMock = useSettings();
		const container = render(
			<div>
				<div className="woocommerce-layout__header-wrapper">
					<div className="woocommerce-layout__header-heading"></div>
				</div>
				<FraudProtectionAdvancedSettingsPage />
			</div>
		);
		const saveButton = await container.findByText( 'Save Changes' );
		saveButton.click();
		await waitFor( () => {
			expect( settingsMock.saveSettings.mock.calls.length ).toBe( 1 );
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
		useCurrentProtectionLevel.mockReturnValue( [
			protectionLevelState.state,
			protectionLevelState.updateState,
		] );
		defaultSettings.push( {
			key: 'purchase_price_threshold',
			outcome: 'block',
			check: {
				operator: 'or',
				checks: [
					{ key: 'order_total', operator: 'less_than', value: 1 },
					{ key: 'order_total', operator: 'greater_than', value: 10 },
				],
			},
		} );
		useSettings.mockReturnValue( {
			settings: {
				advanced_fraud_protection_settings: defaultSettings,
			},
			saveSettings: jest.fn(),
			isLoading: false,
		} );
		const settingsMock = useSettings();
		const container = render(
			<div>
				<div className="woocommerce-layout__header-wrapper">
					<div className="woocommerce-layout__header-heading"></div>
				</div>
				<FraudProtectionAdvancedSettingsPage />
			</div>
		);
		const saveButton = await container.findByText( 'Save Changes' );
		saveButton.click();
		await waitFor( () => {
			expect( settingsMock.saveSettings.mock.calls.length ).toBe( 1 );
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
		useCurrentProtectionLevel.mockReturnValue( [
			protectionLevelState.state,
			protectionLevelState.updateState,
		] );
		defaultSettings.push( {
			key: 'purchase_price_threshold',
			outcome: 'block',
			check: {
				operator: 'or',
				checks: [
					{ key: 'order_total', operator: 'less_than', value: 1 },
					{ key: 'order_total', operator: 'greater_than', value: 10 },
				],
			},
		} );
		useSettings.mockReturnValue( {
			settings: {
				advanced_fraud_protection_settings: defaultSettings,
			},
			saveSettings: jest.fn(),
			isLoading: false,
		} );
		const settingsMock = useSettings();
		const container = render(
			<div>
				<div className="woocommerce-layout__header-wrapper">
					<div className="woocommerce-layout__header-heading"></div>
				</div>
				<FraudProtectionAdvancedSettingsPage />
			</div>
		);
		const saveButton = await container.findByText( 'Save Changes' );
		saveButton.click();
		await waitFor( () => {
			expect( settingsMock.saveSettings.mock.calls.length ).toBe( 1 );
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
