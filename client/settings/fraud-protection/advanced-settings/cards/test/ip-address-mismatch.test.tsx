/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
/**
 * Internal dependencies
 */
import FraudPreventionSettingsContext from '../../context';
import IPAddressMismatchRuleCard from '../ip-address-mismatch';

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
		isFRTReviewFeatureActive: boolean;
	};
};

describe( 'International billing address card', () => {
	const settings = {
		ip_address_mismatch: {
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
	global.wcpaySettings = {
		isFRTReviewFeatureActive: false,
	};
	test( 'renders correctly', () => {
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<IPAddressMismatchRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled', () => {
		settings.ip_address_mismatch.enabled = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<IPAddressMismatchRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled and checked', () => {
		settings.ip_address_mismatch.enabled = true;
		settings.ip_address_mismatch.block = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<IPAddressMismatchRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders like disabled when checked, but not enabled', () => {
		settings.ip_address_mismatch.enabled = false;
		settings.ip_address_mismatch.block = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<IPAddressMismatchRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
