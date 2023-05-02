/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
/**
 * Internal dependencies
 */
import FraudPreventionSettingsContext from '../../context';
import PurchasePriceThresholdRuleCard, {
	PurchasePriceThresholdValidation,
} from '../purchase-price-threshold';
import { FraudPreventionPurchasePriceThresholdSetting } from 'wcpay/settings/fraud-protection/interfaces';

declare const global: {
	wcpaySettings: {
		storeCurrency: string;
		connect: {
			country: string;
		};
		currencyData: {
			[ key: string ]: {
				code: string;
				symbol: string;
				symbolPosition: string;
				thousandSeparator: string;
				decimalSeparator: string;
				precision: number;
			};
		};
	};
};

describe( 'Purchase price threshold card', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			storeCurrency: 'USD',
			connect: {
				country: 'US',
			},
			currencyData: {
				US: {
					code: 'USD',
					symbol: '$',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
			},
		};
	} );

	const settings = {
		purchase_price_threshold: {
			enabled: false,
			block: false,
			min_amount: null,
			max_amount: null,
		} as FraudPreventionPurchasePriceThresholdSetting,
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
				'A price range must be set for this filter to take effect.',
				{
					ignore: '.a11y-speak-region',
				}
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
				'A price range must be set for this filter to take effect.',
				{
					ignore: '.a11y-speak-region',
				}
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
				'A price range must be set for this filter to take effect.',
				{
					ignore: '.a11y-speak-region',
				}
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
				'A price range must be set for this filter to take effect.',
				{
					ignore: '.a11y-speak-region',
				}
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
				'Maximum purchase price must be greater than the minimum purchase price.',
				{
					ignore: '.a11y-speak-region',
				}
			)
		).toBeInTheDocument();
	} );
	test( 'validation returns true when `min_amount` is set', () => {
		settings.purchase_price_threshold.enabled = true;
		settings.purchase_price_threshold.block = false;
		settings.purchase_price_threshold.max_amount = null;
		settings.purchase_price_threshold.min_amount = 100;
		const setValidationError = jest.fn();
		const validationResult = PurchasePriceThresholdValidation(
			settings.purchase_price_threshold,
			setValidationError
		);
		expect( validationResult ).toBe( true );
		expect( setValidationError.mock.calls.length ).toBe( 0 );
	} );
	test( 'validation returns true when `max_amount` is set', () => {
		settings.purchase_price_threshold.enabled = true;
		settings.purchase_price_threshold.block = false;
		settings.purchase_price_threshold.max_amount = 100;
		settings.purchase_price_threshold.min_amount = null;
		const setValidationError = jest.fn();
		const validationResult = PurchasePriceThresholdValidation(
			settings.purchase_price_threshold,
			setValidationError
		);
		expect( validationResult ).toBe( true );
		expect( setValidationError.mock.calls.length ).toBe( 0 );
	} );
	test( 'validation returns true when both are set', () => {
		settings.purchase_price_threshold.enabled = true;
		settings.purchase_price_threshold.block = false;
		settings.purchase_price_threshold.max_amount = 100;
		settings.purchase_price_threshold.min_amount = 10;
		const setValidationError = jest.fn();
		const validationResult = PurchasePriceThresholdValidation(
			settings.purchase_price_threshold,
			setValidationError
		);
		expect( validationResult ).toBe( true );
		expect( setValidationError.mock.calls.length ).toBe( 0 );
	} );
	test( 'validation returns true when setting is not enabled', () => {
		settings.purchase_price_threshold.enabled = false;
		settings.purchase_price_threshold.block = false;
		settings.purchase_price_threshold.max_amount = null;
		settings.purchase_price_threshold.min_amount = null;
		const setValidationError = jest.fn();
		const validationResult = PurchasePriceThresholdValidation(
			settings.purchase_price_threshold,
			setValidationError
		);
		expect( validationResult ).toBe( true );
		expect( setValidationError.mock.calls.length ).toBe( 0 );
	} );
	test( 'validation returns false when amounts are not set', () => {
		settings.purchase_price_threshold.enabled = true;
		settings.purchase_price_threshold.block = false;
		settings.purchase_price_threshold.max_amount = null;
		settings.purchase_price_threshold.min_amount = null;
		const setValidationError = jest.fn();
		const validationResult = PurchasePriceThresholdValidation(
			settings.purchase_price_threshold,
			setValidationError
		);
		expect( validationResult ).toBe( false );
		expect( setValidationError.mock.calls.length ).toBe( 1 );
		expect( setValidationError.mock.calls[ 0 ] ).toContain(
			'A price range must be set for the "Purchase Price threshold" filter.'
		);
	} );
	test( 'validation returns false when min amount is greater than max amount', () => {
		settings.purchase_price_threshold.enabled = true;
		settings.purchase_price_threshold.block = false;
		settings.purchase_price_threshold.max_amount = 10;
		settings.purchase_price_threshold.min_amount = 100;
		const setValidationError = jest.fn();
		const validationResult = PurchasePriceThresholdValidation(
			settings.purchase_price_threshold,
			setValidationError
		);
		expect( validationResult ).toBe( false );
		expect( setValidationError.mock.calls.length ).toBe( 1 );
		expect( setValidationError.mock.calls[ 0 ] ).toContain(
			'Maximum purchase price must be greater than the minimum purchase price.'
		);
	} );
} );
