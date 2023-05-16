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

declare const global: {
	wcpaySettings: {
		accountStatus: {
			fraudProtection: {
				declineOnAVSFailure: boolean;
			};
		};
		isFRTReviewFeatureActive?: boolean;
	};
};

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
			isFRTReviewFeatureActive: false,
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
	} );
} );
