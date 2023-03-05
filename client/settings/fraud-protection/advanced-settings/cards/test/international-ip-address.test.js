/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
/**
 * Internal dependencies
 */
import FraudPreventionSettingsContext from '../../context';
import InternationalIPAddressRuleCard from '../international-ip-address';

describe( 'International IP address card', () => {
	test( 'renders correctly', () => {
		const settings = {
			international_ip_address: {
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
				<InternationalIPAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled', () => {
		const settings = {
			international_ip_address: {
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
				<InternationalIPAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled and checked', () => {
		const settings = {
			international_ip_address: {
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
				<InternationalIPAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders like disabled when checked, but not enabled', () => {
		const settings = {
			international_ip_address: {
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
				<InternationalIPAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
