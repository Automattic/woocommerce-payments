/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
/**
 * Internal dependencies
 */
import AddressMismatchRuleCard from '../address-mismatch';
import FraudPreventionSettingsContext from '../../context';

describe( 'Address mismatch card', () => {
	const settings = {
		address_mismatch: {
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
	test( 'renders correctly', () => {
		settings.address_mismatch.enabled = false;
		settings.address_mismatch.block = false;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<AddressMismatchRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled', () => {
		settings.address_mismatch.enabled = true;
		settings.address_mismatch.block = false;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<AddressMismatchRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders correctly when enabled and checked', () => {
		settings.address_mismatch.enabled = true;
		settings.address_mismatch.block = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<AddressMismatchRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
	test( 'renders like disabled when checked, but not enabled', () => {
		settings.address_mismatch.enabled = false;
		settings.address_mismatch.block = true;
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<AddressMismatchRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
