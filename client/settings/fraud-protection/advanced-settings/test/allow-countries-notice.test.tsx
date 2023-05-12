/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
/**
 * Internal dependencies
 */
import AllowedCountriesNotice from '../allow-countries-notice';
import FraudPreventionSettingsContext from '../context';

const mockContext = {
	protectionSettingsUI: {
		test_key: {
			enabled: false,
			block: false,
		},
	},
	protectionSettingsChanged: false,
	setProtectionSettingsUI: jest.fn(),
	setProtectionSettingsChanged: jest.fn(),
};

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
};

describe( 'Allowed countries rule card notice tests', () => {
	beforeAll( () => {
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
	} );
	test( 'renders correctly when all countries are allowed', () => {
		const container = render(
			<FraudPreventionSettingsContext.Provider value={ mockContext }>
				<AllowedCountriesNotice setting={ 'test_key' } />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect( container.baseElement ).toHaveTextContent(
			/Enabling this filter will not have any effect because you are selling to all countries\./i
		);
	} );
	test( 'renders correctly when specific countries are allowed, others will be hold', () => {
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'specific';
		global.wcSettings.admin.preloadSettings.general.woocommerce_specific_allowed_countries = [
			'CA',
			'US',
		];
		mockContext.protectionSettingsUI.test_key.block = false;

		const container = render(
			<FraudPreventionSettingsContext.Provider value={ mockContext }>
				<AllowedCountriesNotice setting={ 'test_key' } />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect( container.baseElement ).toHaveTextContent(
			/Orders from outside of the following countries will be screened by the filter: Canada, United States/i
		);
	} );
	test( 'renders correctly when specific countries are allowed, others will be blocked', () => {
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'specific';
		global.wcSettings.admin.preloadSettings.general.woocommerce_specific_allowed_countries = [
			'CA',
			'US',
		];
		mockContext.protectionSettingsUI.test_key.block = true;

		const container = render(
			<FraudPreventionSettingsContext.Provider value={ mockContext }>
				<AllowedCountriesNotice setting={ 'test_key' } />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect( container.baseElement ).toHaveTextContent(
			/Orders from outside of the following countries will be blocked by the filter: Canada, United States/i
		);
	} );
	test( 'renders correctly when countries except some are allowed, others will be hold', () => {
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'all_except';
		global.wcSettings.admin.preloadSettings.general.woocommerce_all_except_countries = [
			'CA',
			'US',
		];
		mockContext.protectionSettingsUI.test_key.block = false;

		const container = render(
			<FraudPreventionSettingsContext.Provider value={ mockContext }>
				<AllowedCountriesNotice setting={ 'test_key' } />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect( container.baseElement ).toHaveTextContent(
			/Orders from the following countries will be screened by the filter: Canada, United States/i
		);
	} );
	test( 'renders correctly when countries except some are allowed, others will be blocked', () => {
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'all_except';
		global.wcSettings.admin.preloadSettings.general.woocommerce_all_except_countries = [
			'CA',
			'US',
		];
		mockContext.protectionSettingsUI.test_key.block = true;

		const container = render(
			<FraudPreventionSettingsContext.Provider value={ mockContext }>
				<AllowedCountriesNotice setting={ 'test_key' } />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect( container.baseElement ).toHaveTextContent(
			/Orders from the following countries will be blocked by the filter: Canada, United States/i
		);
	} );
} );
