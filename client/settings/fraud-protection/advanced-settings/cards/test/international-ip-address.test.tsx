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
};

describe( 'International IP address card', () => {
	const settings = {
		international_ip_address: {
			enabled: false,
			block: false,
		},
	};
	const setSettings = jest.fn();
	const contextValue = {
		protectionSettingsUI: settings,
		setProtectionSettingsUI: setSettings,
		protectionSettingsChanged: false,
		setProtectionSettingsChanged: jest.fn(),
	};
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
	test( 'renders correctly', () => {
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<InternationalIPAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled', () => {
		settings.international_ip_address.enabled = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<InternationalIPAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled and checked', () => {
		settings.international_ip_address.enabled = true;
		settings.international_ip_address.block = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<InternationalIPAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders like disabled when checked, but not enabled', () => {
		settings.international_ip_address.enabled = false;
		settings.international_ip_address.block = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<InternationalIPAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
