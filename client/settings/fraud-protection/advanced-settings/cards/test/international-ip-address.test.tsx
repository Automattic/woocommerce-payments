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
		countries: {
			[ key: string ]: string;
		};
	};

	wcpaySettings: {
		isFRTReviewFeatureActive: boolean;
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
		isDirty: false,
		setIsDirty: jest.fn(),
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
		countries: {
			CA: 'Canada',
			US: 'United States',
		},
	};
	global.wcpaySettings = {
		isFRTReviewFeatureActive: false,
	};
	test( 'renders correctly when woocommerce_allowed_countries is all', () => {
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<InternationalIPAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when woocommerce_allowed_countries is specific', () => {
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'specific';
		global.wcSettings.admin.preloadSettings.general.woocommerce_specific_allowed_countries = [
			'CA',
			'US',
		];
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<InternationalIPAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when woocommerce_allowed_countries is all_except', () => {
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'all_except';
		global.wcSettings.admin.preloadSettings.general.woocommerce_all_except_countries = [
			'CA',
			'US',
		];
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<InternationalIPAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled', () => {
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'specific';
		global.wcSettings.admin.preloadSettings.general.woocommerce_specific_allowed_countries = [
			'CA',
			'US',
		];
		settings.international_ip_address.enabled = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<InternationalIPAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled and checked', () => {
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'specific';
		global.wcSettings.admin.preloadSettings.general.woocommerce_specific_allowed_countries = [
			'CA',
			'US',
		];
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
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'specific';
		global.wcSettings.admin.preloadSettings.general.woocommerce_specific_allowed_countries = [
			'CA',
			'US',
		];
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
