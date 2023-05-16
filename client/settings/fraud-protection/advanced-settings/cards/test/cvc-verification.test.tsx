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

declare const global: {
	wcpaySettings: {
		accountStatus: {
			fraudProtection: {
				declineOnCVCFailure: boolean;
			};
		};
	};
};

describe( 'CVC verification card', () => {
	test( 'renders correctly when CVC check is enabled', () => {
		const settings = {
			cvc_verification: {
				enabled: false,
				block: false,
			},
		};
		global.wcpaySettings = {
			accountStatus: {
				fraudProtection: {
					declineOnCVCFailure: true,
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
				<CVCVerificationRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect( container ).toHaveTextContent(
			/For security, this filter is enabled and cannot be modified/i
		);
	} );
	test( 'renders correctly when CVC check is disabled', () => {
		const settings = {
			cvc_verification: {
				enabled: false,
				block: false,
			},
		};
		global.wcpaySettings = {
			accountStatus: {
				fraudProtection: {
					declineOnCVCFailure: false,
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
				<CVCVerificationRuleCard />
			</FraudPreventionSettingsContext.Provider>
		);
		expect( container ).toMatchSnapshot();
		expect( container ).toHaveTextContent(
			/This filter is disabled, and can not be modified/i
		);
	} );
} );
