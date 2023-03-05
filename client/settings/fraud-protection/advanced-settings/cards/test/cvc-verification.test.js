/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
/**
 * Internal dependencies
 */
import FraudPreventionSettingsContext from '../../context';
import CVCVerificationRuleCard from '../cvc-verification';

describe( 'CVC verification card', () => {
	test( 'renders correctly', () => {
		const settings = {
			cvc_verification: {
				enabled: false,
				block: false,
			},
		};
		const setSettings = jest.fn();
		const contextValue = {
			advancedFraudProtectionSettings: settings,
			setAdvancedFraudProtectionSettings: setSettings,
		};
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<CVCVerificationRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
