/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
/**
 * Internal dependencies
 */
import FraudPreventionSettingsContext from '../../context';
import OrderVelocityRuleCard, {
	OrderVelocityValidation,
} from '../order-velocity';

describe( 'Order velocity card', () => {
	const settings = {
		order_velocity: {
			enabled: false,
			block: false,
			max_orders: null,
			interval: null,
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
				<OrderVelocityRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled', () => {
		settings.order_velocity.enabled = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderVelocityRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled and checked', () => {
		settings.order_velocity.enabled = true;
		settings.order_velocity.block = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderVelocityRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders like disabled when checked, but not enabled', () => {
		settings.order_velocity.enabled = false;
		settings.order_velocity.block = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderVelocityRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'render warning when max orders field is not filled', () => {
		settings.order_velocity.enabled = true;
		settings.order_velocity.block = true;
		settings.order_velocity.max_orders = null;
		settings.order_velocity.interval = 12;
		render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<OrderVelocityRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect(
			screen.queryByText(
				'A maximum order count must be set for this filter to take effect.'
			)
		).toBeInTheDocument();
	} );
	test( 'validation returns true when `max_orders` is set', () => {
		settings.order_velocity.block = false;
		settings.order_velocity.max_orders = 100;
		const setValidationError = jest.fn();
		const validationResult = OrderVelocityValidation(
			settings.order_velocity,
			setValidationError
		);
		expect( validationResult ).toBe( true );
		expect( setValidationError.mock.calls.length ).toBe( 0 );
	} );
	test( 'validation returns true when setting is not enabled', () => {
		settings.order_velocity.enabled = false;
		settings.order_velocity.block = false;
		settings.order_velocity.max_orders = null;
		const setValidationError = jest.fn();
		const validationResult = OrderVelocityValidation(
			settings.order_velocity,
			setValidationError
		);
		expect( validationResult ).toBe( true );
		expect( setValidationError.mock.calls.length ).toBe( 0 );
	} );
	test( 'validation returns false when max_orders is not set', () => {
		settings.order_velocity.enabled = true;
		settings.order_velocity.block = false;
		settings.order_velocity.max_orders = null;
		const setValidationError = jest.fn();
		const validationResult = OrderVelocityValidation(
			settings.order_velocity,
			setValidationError
		);
		expect( validationResult ).toBe( false );
		expect( setValidationError.mock.calls.length ).toBe( 1 );
		expect( setValidationError.mock.calls[ 0 ] ).toContain(
			'A maximum order count must be set for the "Order Velocity" filter.'
		);
	} );
} );
