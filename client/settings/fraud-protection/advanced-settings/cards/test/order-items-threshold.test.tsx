/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
/**
 * Internal dependencies
 */
import FraudPreventionSettingsContext from '../../context';
import OrderItemsThresholdRuleCard, {
	OrderItemsThresholdValidation,
} from '../order-items-threshold';
import { FraudPreventionOrderItemsThresholdSetting } from 'wcpay/settings/fraud-protection/interfaces';

describe( 'Order items threshold card', () => {
	const settings = {
		order_items_threshold: {
			enabled: false,
			block: false,
			min_items: null,
			max_items: null,
		} as FraudPreventionOrderItemsThresholdSetting,
	};
	const setSettings = jest.fn();
	const contextValue = {
		protectionSettingsUI: settings,
		setProtectionSettingsUI: setSettings,
		protectionSettingsChanged: false,
		setProtectionSettingsChanged: jest.fn(),
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
				'An item range must be set for this filter to take effect.',
				{
					ignore: '.a11y-speak-region',
				}
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
				'An item range must be set for this filter to take effect.',
				{
					ignore: '.a11y-speak-region',
				}
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
				'An item range must be set for this filter to take effect.',
				{
					ignore: '.a11y-speak-region',
				}
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
				'An item range must be set for this filter to take effect.',
				{
					ignore: '.a11y-speak-region',
				}
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
				'An item range must be set for this filter to take effect.',
				{
					ignore: '.a11y-speak-region',
				}
			)
		).not.toBeInTheDocument();
		expect(
			screen.queryByText(
				'Maximum item count must be greater than the minimum item count.',
				{
					ignore: '.a11y-speak-region',
				}
			)
		).toBeInTheDocument();
	} );

	test( 'validation returns true when `min_items` is set', () => {
		settings.order_items_threshold.enabled = true;
		settings.order_items_threshold.block = false;
		settings.order_items_threshold.max_items = null;
		settings.order_items_threshold.min_items = 100;
		const setValidationError = jest.fn();
		const validationResult = OrderItemsThresholdValidation(
			settings.order_items_threshold,
			setValidationError
		);
		expect( validationResult ).toBe( true );
		expect( setValidationError.mock.calls.length ).toBe( 0 );
	} );
	test( 'validation returns true when `max_amount` is set', () => {
		settings.order_items_threshold.enabled = true;
		settings.order_items_threshold.block = false;
		settings.order_items_threshold.max_items = 100;
		settings.order_items_threshold.min_items = null;
		const setValidationError = jest.fn();
		const validationResult = OrderItemsThresholdValidation(
			settings.order_items_threshold,
			setValidationError
		);
		expect( validationResult ).toBe( true );
		expect( setValidationError.mock.calls.length ).toBe( 0 );
	} );
	test( 'validation returns true when both are set', () => {
		settings.order_items_threshold.enabled = true;
		settings.order_items_threshold.block = false;
		settings.order_items_threshold.max_items = 100;
		settings.order_items_threshold.min_items = 10;
		const setValidationError = jest.fn();
		const validationResult = OrderItemsThresholdValidation(
			settings.order_items_threshold,
			setValidationError
		);
		expect( validationResult ).toBe( true );
		expect( setValidationError.mock.calls.length ).toBe( 0 );
	} );
	test( 'validation returns true when setting is not enabled', () => {
		settings.order_items_threshold.enabled = false;
		settings.order_items_threshold.block = false;
		settings.order_items_threshold.max_items = null;
		settings.order_items_threshold.min_items = null;
		const setValidationError = jest.fn();
		const validationResult = OrderItemsThresholdValidation(
			settings.order_items_threshold,
			setValidationError
		);
		expect( validationResult ).toBe( true );
		expect( setValidationError.mock.calls.length ).toBe( 0 );
	} );
	test( 'validation returns false when amounts are not set', () => {
		settings.order_items_threshold.enabled = true;
		settings.order_items_threshold.block = false;
		settings.order_items_threshold.max_items = null;
		settings.order_items_threshold.min_items = null;
		const setValidationError = jest.fn();
		const validationResult = OrderItemsThresholdValidation(
			settings.order_items_threshold,
			setValidationError
		);
		expect( validationResult ).toBe( false );
		expect( setValidationError.mock.calls.length ).toBe( 1 );
		expect( setValidationError.mock.calls[ 0 ] ).toContain(
			'An item range must be set for the "Order Item Threshold" filter.'
		);
	} );
	test( 'validation returns false when min amount is greater than max amount', () => {
		settings.order_items_threshold.enabled = true;
		settings.order_items_threshold.block = false;
		settings.order_items_threshold.max_items = 10;
		settings.order_items_threshold.min_items = 100;
		const setValidationError = jest.fn();
		const validationResult = OrderItemsThresholdValidation(
			settings.order_items_threshold,
			setValidationError
		);
		expect( validationResult ).toBe( false );
		expect( setValidationError.mock.calls.length ).toBe( 1 );
		expect( setValidationError.mock.calls[ 0 ] ).toContain(
			'Maximum item count must be greater than the minimum item count on the "Order Item Threshold" rule.'
		);
	} );
} );
