/**
 * External dependencies
 */
import React from 'react';
import { render, waitFor } from '@testing-library/react';
/**
 * Internal dependencies
 */
import FraudProtectionAdvancedSettingsPage from '..';
import { useSettings } from '../../../../data';

jest.mock( '../../../../data', () => ( {
	useSettings: jest.fn(),
} ) );

const defaultSettings = {
	avs_mismatch: {
		enabled: false,
		block: false,
	},
	cvc_verification: {
		enabled: false,
		block: false,
	},
	address_mismatch: {
		enabled: false,
		block: false,
	},
	international_ip_address: {
		enabled: false,
		block: false,
	},
	international_billing_address: {
		enabled: false,
		block: false,
	},
	order_velocity: {
		enabled: false,
		block: false,
		max_orders: 0,
		interval: 12,
	},
	order_items_threshold: {
		enabled: false,
		block: false,
		min_items: 0,
		max_items: 0,
	},
	purchase_price_threshold: {
		enabled: false,
		block: false,
		min_amount: 0,
		max_amount: 0,
	},
};

describe( 'Advanced fraud protection settings', () => {
	beforeEach( () => {
		useSettings.mockReturnValue( {
			settings: {
				advanced_fraud_protection_settings: { ...defaultSettings },
			},
			saveSettings: jest.fn(),
			isLoading: false,
		} );
		window.scrollTo = jest.fn();
	} );
	test( 'renders correctly', () => {
		const container = render( <FraudProtectionAdvancedSettingsPage /> );
		expect( container ).toMatchSnapshot();
	} );
	test( "doesn't save when there's validation errors", async () => {
		const settingsMock = useSettings();
		defaultSettings.purchase_price_threshold.enabled = true;
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
		expect( settingsMock.saveSettings.mock.calls.length ).toBe( 0 );
		expect(
			document.querySelectorAll(
				'.fraud-protection-advanced-settings-error-notice'
			).length
		).toBe( 1 );
	} );
	test( 'saves settings when there are no validation errors', async () => {
		const settingsMock = useSettings();
		defaultSettings.purchase_price_threshold.enabled = true;
		defaultSettings.purchase_price_threshold.min_amount = 1;
		defaultSettings.purchase_price_threshold.max_amount = 10;
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
	} );
} );
