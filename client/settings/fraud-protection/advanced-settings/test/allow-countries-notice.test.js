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
	advancedFraudProtectionSettings: {
		test_key: {
			enabled: false,
			block: false,
		},
	},
	setAdvancedFraudProtectionSettings: jest.fn(),
	setProtectionSettingsChanged: jest.fn(),
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
		mockContext.advancedFraudProtectionSettings.test_key.block = false;

		const container = render(
			<FraudPreventionSettingsContext.Provider value={ mockContext }>
				<AllowedCountriesNotice setting={ 'test_key' } />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect( container.baseElement ).toHaveTextContent(
			/Orders outside from these countries will be screened by the filter: Canada, United States/i
		);
	} );
	test( 'renders correctly when specific countries are allowed, others will be blocked', () => {
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'specific';
		global.wcSettings.admin.preloadSettings.general.woocommerce_specific_allowed_countries = [
			'CA',
			'US',
		];
		mockContext.advancedFraudProtectionSettings.test_key.block = true;

		const container = render(
			<FraudPreventionSettingsContext.Provider value={ mockContext }>
				<AllowedCountriesNotice setting={ 'test_key' } />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect( container.baseElement ).toHaveTextContent(
			/Orders outside from these countries will be blocked by the filter: Canada, United States/i
		);
	} );
	test( 'renders correctly when countries except some are allowed, others will be hold', () => {
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'all_except';
		global.wcSettings.admin.preloadSettings.general.woocommerce_all_except_countries = [
			'CA',
			'US',
		];
		mockContext.advancedFraudProtectionSettings.test_key.block = false;

		const container = render(
			<FraudPreventionSettingsContext.Provider value={ mockContext }>
				<AllowedCountriesNotice setting={ 'test_key' } />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect( container.baseElement ).toHaveTextContent(
			/Orders from these countries will be screened by the filter: Canada, United States/i
		);
	} );
	test( 'renders correctly when countries except some are allowed, others will be blocked', () => {
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'all_except';
		global.wcSettings.admin.preloadSettings.general.woocommerce_all_except_countries = [
			'CA',
			'US',
		];
		mockContext.advancedFraudProtectionSettings.test_key.block = true;

		const container = render(
			<FraudPreventionSettingsContext.Provider value={ mockContext }>
				<AllowedCountriesNotice setting={ 'test_key' } />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect( container.baseElement ).toHaveTextContent(
			/Orders from these countries will be blocked by the filter: Canada, United States/i
		);
	} );
} );
