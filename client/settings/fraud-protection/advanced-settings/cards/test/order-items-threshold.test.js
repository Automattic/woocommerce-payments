/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
/**
 * Internal dependencies
 */
import FraudPreventionSettingsContext from '../../context';
import OrderItemsThresholdRuleCard from '../order-items-threshold';

describe( 'Order items threshold card', () => {
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
	test( 'renders correctly', () => {
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderItemsThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled', () => {
		settings.order_items_threshold.enabled = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderItemsThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled and checked', () => {
		settings.order_items_threshold.enabled = true;
		settings.order_items_threshold.block = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderItemsThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders like disabled when checked, but not enabled', () => {
		settings.order_items_threshold.enabled = false;
		settings.order_items_threshold.block = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderItemsThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders warning when both fields are empty', () => {
		settings.order_items_threshold.enabled = true;
		settings.order_items_threshold.block = true;
		render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderItemsThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect(
			screen.queryByText(
				'An item range must be set for this filter to take effect.'
			)
		).toBeInTheDocument();
	} );
	test( "doesn't render warning when only min items field is filled", () => {
		settings.order_items_threshold.enabled = true;
		settings.order_items_threshold.block = true;
		settings.order_items_threshold.min_items = 1;
		settings.order_items_threshold.max_items = null;
		render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderItemsThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect(
			screen.queryByText(
				'An item range must be set for this filter to take effect.'
			)
		).not.toBeInTheDocument();
	} );
	test( "doesn't render warning when only max items field is filled", () => {
		settings.order_items_threshold.enabled = true;
		settings.order_items_threshold.block = true;
		settings.order_items_threshold.min_items = null;
		settings.order_items_threshold.max_items = 1;
		render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderItemsThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect(
			screen.queryByText(
				'An item range must be set for this filter to take effect.'
			)
		).not.toBeInTheDocument();
	} );
	test( "doesn't render warning when both fields are filled", () => {
		settings.order_items_threshold.enabled = true;
		settings.order_items_threshold.block = true;
		settings.order_items_threshold.min_items = 1;
		settings.order_items_threshold.max_items = 2;
		render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderItemsThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect(
			screen.queryByText(
				'An item range must be set for this filter to take effect.'
			)
		).not.toBeInTheDocument();
	} );
	test( 'renders error when min items is greater than max items', () => {
		settings.order_items_threshold.enabled = true;
		settings.order_items_threshold.block = true;
		settings.order_items_threshold.min_items = 2;
		settings.order_items_threshold.max_items = 1;
		render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderItemsThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect(
			screen.queryByText(
				'An item range must be set for this filter to take effect.'
			)
		).not.toBeInTheDocument();
		expect(
			screen.queryByText(
				'Maximum item count must be greater than the minimum item count.'
			)
		).toBeInTheDocument();
	} );
} );
