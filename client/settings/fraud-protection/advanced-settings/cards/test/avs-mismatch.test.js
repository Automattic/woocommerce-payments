/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
/**
 * Internal dependencies
 */
import FraudPreventionSettingsContext from '../../context';
import AVSMismatchRuleCard from '../avs-mismatch';

describe( 'AVS mismatch card', () => {
	test( 'renders correctly', () => {
		const settings = {
			avs_mismatch: {
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
				<AVSMismatchRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
