/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
/**
 * Internal dependencies
 */
import FraudPreventionSettingsContext from '../../context';
import InternationalBillingAddressRuleCard from '../international-billing-address';

describe( 'International billing address card', () => {
	const settings = {
		international_billing_address: {
			enabled: false,
			block: false,
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
				<InternationalBillingAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled', () => {
		settings.international_billing_address.enabled = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<InternationalBillingAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled and checked', () => {
		settings.international_billing_address.enabled = true;
		settings.international_billing_address.block = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<InternationalBillingAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders like disabled when checked, but not enabled', () => {
		settings.international_billing_address.enabled = false;
		settings.international_billing_address.block = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<InternationalBillingAddressRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
