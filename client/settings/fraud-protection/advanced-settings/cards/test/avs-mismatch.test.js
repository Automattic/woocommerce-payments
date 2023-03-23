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
	test( 'renders correctly when AVS check is enabled', () => {
		const settings = {
			avs_mismatch: {
				enabled: false,
				block: false,
			},
		};
		global.wcpaySettings = {
			accountStatus: {
				fraudProtection: {
					declineOnAVSFailure: true,
				},
			},
		};
		const setSettings = jest.fn();
		const contextValue = {
			protectionSettingsUI: settings,
			setProtectionSettingsUI: setSettings,
			protectionSettingsChanged: false,
			setProtectionSettingsChanged: jest.fn(),
		};
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<AVSMismatchRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect( container ).toHaveTextContent(
			/For security, this filter is enabled and cannot be modified/i
		);
	} );
	test( 'renders correctly when AVS check is disabled', () => {
		const settings = {
			avs_mismatch: {
				enabled: false,
				block: false,
			},
		};
		global.wcpaySettings = {
			accountStatus: {
				fraudProtection: {
					declineOnAVSFailure: false,
				},
			},
		};
		const setSettings = jest.fn();
		const contextValue = {
			protectionSettingsUI: settings,
			setProtectionSettingsUI: setSettings,
			protectionSettingsChanged: false,
			setProtectionSettingsChanged: jest.fn(),
		};
		const { container } = render(
			<FraudPreventionSettingsContext.Provider value={ contextValue }>
				<AVSMismatchRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect( container ).toHaveTextContent(
			/This filter is disabled, and can not be modified/i
		);
	} );
} );
