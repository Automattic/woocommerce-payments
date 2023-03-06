/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
/**
 * Internal dependencies
 */
import FraudPreventionSettingsContext from '../../context';
import PurchasePriceThresholdRuleCard from '../purchase-price-threshold';

describe( 'Purchase price threshold card', () => {
	const settings = {
		purchase_price_threshold: {
			enabled: false,
			block: false,
			min_amount: null,
			max_amount: null,
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
				<PurchasePriceThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled', () => {
		settings.purchase_price_threshold.enabled = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<PurchasePriceThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled and checked', () => {
		settings.purchase_price_threshold.enabled = true;
		settings.purchase_price_threshold.block = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<PurchasePriceThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders like disabled when checked, but not enabled', () => {
		settings.purchase_price_threshold.enabled = false;
		settings.purchase_price_threshold.block = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<PurchasePriceThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders warning when both fields are empty', () => {
		settings.purchase_price_threshold.enabled = true;
		settings.purchase_price_threshold.block = true;
		render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<PurchasePriceThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect(
			screen.queryByText(
				'A price range must be set for this filter to take effect.'
			)
		).toBeInTheDocument();
	} );
	test( "doesn't render warning when only min items field is filled", () => {
		settings.purchase_price_threshold.enabled = true;
		settings.purchase_price_threshold.block = true;
		settings.purchase_price_threshold.min_amount = 1;
		settings.purchase_price_threshold.max_amount = null;
		render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<PurchasePriceThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect(
			screen.queryByText(
				'A price range must be set for this filter to take effect.'
			)
		).not.toBeInTheDocument();
	} );
	test( "doesn't render warning when only max items field is filled", () => {
		settings.purchase_price_threshold.enabled = true;
		settings.purchase_price_threshold.block = true;
		settings.purchase_price_threshold.min_amount = null;
		settings.purchase_price_threshold.max_amount = 1;
		render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<PurchasePriceThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect(
			screen.queryByText(
				'A price range must be set for this filter to take effect.'
			)
		).not.toBeInTheDocument();
	} );
	test( "doesn't render warning when both fields are filled", () => {
		settings.purchase_price_threshold.enabled = true;
		settings.purchase_price_threshold.block = true;
		settings.purchase_price_threshold.min_amount = 1;
		settings.purchase_price_threshold.max_amount = 2;
		render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<PurchasePriceThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect(
			screen.queryByText(
				'A price range must be set for this filter to take effect.'
			)
		).not.toBeInTheDocument();
	} );
	test( 'renders error when min items is greater than max items', () => {
		settings.purchase_price_threshold.enabled = true;
		settings.purchase_price_threshold.block = true;
		settings.purchase_price_threshold.min_amount = 2;
		settings.purchase_price_threshold.max_amount = 1;
		render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<PurchasePriceThresholdRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect(
			screen.queryByText(
				'A price range must be set for this filter to take effect.'
			)
		).not.toBeInTheDocument();
		expect(
			screen.queryByText(
				'Maximum purchase price must be greater than the minimum purchase price.'
			)
		).toBeInTheDocument();
	} );
} );
