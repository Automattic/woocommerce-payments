/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
/**
 * Internal dependencies
 */
import FraudPreventionSettingsContext from '../../context';
import OrderItemsThresholdRuleCard from '../order-items-threshold';

describe( 'Order items threshold card', () => {
	test( 'renders correctly', () => {
		const settings = {
			order_items_threshold: {
				enabled: false,
				block: false,
				min_items: null,
				max_items: null,
			},
		};
		const setSettings = jest.fn();
		const contextValue = {
			advancedFraudProtectionSettings: settings,
			setAdvancedFraudProtectionSettings: setSettings,
		};
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderItemsThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled', () => {
		const settings = {
			order_items_threshold: {
				enabled: true,
				block: false,
				min_items: null,
				max_items: null,
			},
		};
		const setSettings = jest.fn();
		const contextValue = {
			advancedFraudProtectionSettings: settings,
			setAdvancedFraudProtectionSettings: setSettings,
		};
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderItemsThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled and checked', () => {
		const settings = {
			order_items_threshold: {
				enabled: true,
				block: true,
				min_items: null,
				max_items: null,
			},
		};
		const setSettings = jest.fn();
		const contextValue = {
			advancedFraudProtectionSettings: settings,
			setAdvancedFraudProtectionSettings: setSettings,
		};
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderItemsThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders like disabled when checked, but not enabled', () => {
		const settings = {
			order_items_threshold: {
				enabled: false,
				block: true,
				min_items: null,
				max_items: null,
			},
		};
		const setSettings = jest.fn();
		const contextValue = {
			advancedFraudProtectionSettings: settings,
			setAdvancedFraudProtectionSettings: setSettings,
		};
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderItemsThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
