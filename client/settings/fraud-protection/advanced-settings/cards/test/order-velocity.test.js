/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
/**
 * Internal dependencies
 */
import AddressMismatchRuleCard from '../address-mismatch';
import FraudPreventionSettingsContext from '../../context';

describe( 'Address mismatch card', () => {
	test( 'renders correctly', () => {
		const settings = {
			address_mismatch: {
				enabled: false,
				block: false,
			},
		};
		const setSettings = jest.fn();
		const contextValue = {
			advancedFraudProtectionSettings: settings,
			setAdvancedFraudProtectionSettings: setSettings,
		};
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<AddressMismatchRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled', () => {
		const settings = {
			address_mismatch: {
				enabled: true,
				block: false,
			},
		};
		const setSettings = jest.fn();
		const contextValue = {
			advancedFraudProtectionSettings: settings,
			setAdvancedFraudProtectionSettings: setSettings,
		};
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<AddressMismatchRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled and checked', () => {
		const settings = {
			address_mismatch: {
				enabled: true,
				block: true,
			},
		};
		const setSettings = jest.fn();
		const contextValue = {
			advancedFraudProtectionSettings: settings,
			setAdvancedFraudProtectionSettings: setSettings,
		};
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<AddressMismatchRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders like disabled when checked, but not enabled', () => {
		const settings = {
			address_mismatch: {
				enabled: false,
				block: true,
			},
		};
		const setSettings = jest.fn();
		const contextValue = {
			advancedFraudProtectionSettings: settings,
			setAdvancedFraudProtectionSettings: setSettings,
		};
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<AddressMismatchRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
