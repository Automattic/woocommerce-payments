/**
 * External dependencies
 */
import React from 'react';
import { render, within } from '@testing-library/react';
/**
 * Internal dependencies
 */
import FraudPreventionSettingsContext from '../../context';
import AVSMismatchRuleCard from '../avs-mismatch';

const renderCard = () => {
	const settings = {
		avs_verification: {
			enabled: false,
			block: false,
		},
	};
	const contextValue = {
		protectionSettingsUI: settings,
		setProtectionSettingsUI: jest.fn(),
		setIsDirty: jest.fn(),
	};
	return render(
		<FraudPreventionSettingsContext.Provider value={ contextValue }>
			<AVSMismatchRuleCard />
		</FraudPreventionSettingsContext.Provider>
	);
};

describe( 'AVS mismatch card', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			accountStatus: {
				fraudProtection: {
					declineOnAVSFailure: true,
				},
			},
			featureFlags: {
				isFRTReviewFeatureActive: false,
			},
		};
		global.wcSettings = {
			admin: {
				preloadSettings: {
					general: {
						woocommerce_allowed_countries: 'all',
						woocommerce_all_except_countries: [],
						woocommerce_specific_allowed_countries: [],
					},
				},
			},
			countries: {
				CA: 'Canada',
				US: 'United States',
				GB: 'United Kingdom',
				DE: 'Germany',
			},
		};
	} );

	test( 'renders correctly when selling to all countries', () => {
		const { container } = renderCard();
		expect( container ).toMatchSnapshot();
	} );

	test( 'does not warn when a specific selling location supports AVS', () => {
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'specific';
		global.wcSettings.admin.preloadSettings.general.woocommerce_specific_allowed_countries =
			[ 'US', 'DE' ];
		const { container } = renderCard();
		expect(
			within( container ).queryByText( /selling locations/i )
		).not.toBeInTheDocument();
	} );

	test( 'warns when no specific selling location supports AVS', () => {
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'specific';
		global.wcSettings.admin.preloadSettings.general.woocommerce_specific_allowed_countries =
			[ 'DE' ];
		const { container } = renderCard();
		expect(
			within( container ).getByText( /selling locations/i )
		).toBeInTheDocument();
	} );

	test( 'warns when every AVS-supported country is excluded', () => {
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'all_except';
		global.wcSettings.admin.preloadSettings.general.woocommerce_all_except_countries =
			[ 'US', 'CA', 'GB' ];
		const { container } = renderCard();
		expect(
			within( container ).getByText( /selling locations/i )
		).toBeInTheDocument();
	} );

	test( 'does not warn when only some AVS-supported countries are excluded', () => {
		global.wcSettings.admin.preloadSettings.general.woocommerce_allowed_countries =
			'all_except';
		global.wcSettings.admin.preloadSettings.general.woocommerce_all_except_countries =
			[ 'US', 'CA' ];
		const { container } = renderCard();
		expect(
			within( container ).queryByText( /selling locations/i )
		).not.toBeInTheDocument();
	} );
} );
