/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import FraudProtectionAdvancedSettingsPage from '..';
import {
	useAdvancedFraudProtectionSettings,
	useCurrentProtectionLevel,
	useSettings,
} from 'wcpay/data/settings';

jest.mock( 'wcpay/data/settings', () => ( {
	useSettings: jest.fn(),
	useCurrentProtectionLevel: jest.fn(),
	useAdvancedFraudProtectionSettings: jest.fn(),
} ) );

// Workaround for mocking @wordpress/data.
// See https://github.com/WordPress/gutenberg/issues/15031
jest.mock( '@wordpress/data', () => ( {
	createRegistryControl: jest.fn(),
	dispatch: jest.fn( () => ( {
		setIsMatching: jest.fn(),
		createSuccessNotice: jest.fn(),
		createErrorNotice: jest.fn(),
		onLoad: jest.fn(),
		onHistoryChange: jest.fn(),
	} ) ),
	registerStore: jest.fn(),
	select: jest.fn(),
	useDispatch: jest.fn( () => ( { createNotice: jest.fn() } ) ),
	withDispatch: jest.fn( () => jest.fn() ),
	withSelect: jest.fn( () => jest.fn() ),
} ) );

const mockUseCurrentProtectionLevel = jest.mocked( useCurrentProtectionLevel );
const mockUseAdvancedFraudProtectionSettings = jest.mocked(
	useAdvancedFraudProtectionSettings
);
const mockUseSettings = useSettings;

const AVS_RULE = {
	key: 'avs_verification',
	outcome: 'block',
	check: null,
};

const setupGlobals = () => {
	window.scrollTo = jest.fn();
	global.wcSettings = {
		admin: {
			preloadSettings: {
				general: {
					woocommerce_allowed_countries: 'specific',
					woocommerce_all_except_countries: [],
					woocommerce_specific_allowed_countries: [ 'CA', 'US' ],
				},
			},
		},
		countries: {
			CA: 'Canada',
			US: 'United States',
		},
		wcVersion: '9.8.1',
	};
	global.wcpaySettings = {
		storeCurrency: 'USD',
		connect: { country: 'US' },
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
		isMultiCurrencyEnabled: '1',
		featureFlags: {
			isFRTReviewFeatureActive: false,
		},
	};
	const mockIntersectionObserver = jest.fn();
	mockIntersectionObserver.mockReturnValue( {
		observe: () => null,
		unobserve: () => null,
		disconnect: () => null,
	} );
	window.IntersectionObserver = mockIntersectionObserver;
};

describe( 'Advanced fraud protection — settings sync pin', () => {
	beforeEach( () => {
		setupGlobals();
		mockUseCurrentProtectionLevel.mockReturnValue( [
			'standard',
			jest.fn(),
		] );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	// This test pins the invariant that `protectionSettingsUI` is kept in
	// sync with the server-loaded `advancedFraudProtectionSettings` within
	// the same render pass — not via a cascading effect. The current
	// implementation uses a render-time state adjustment to preserve the
	// initial-empty-then-populated render order (a11y-speak announcement
	// queue depends on this).
	it( 'reflects a populated ruleset on the same render it arrives', () => {
		mockUseSettings.mockReturnValue( {
			settings: { advanced_fraud_protection_settings: [] },
			saveSettings: jest.fn(),
			isSaving: false,
			isLoading: false,
			isDirty: false,
		} );
		mockUseAdvancedFraudProtectionSettings.mockReturnValue( [
			[],
			jest.fn(),
		] );

		const { getByLabelText, rerender } = render(
			<FraudProtectionAdvancedSettingsPage />
		);

		// Initial render: empty ruleset → AVS toggle is unchecked.
		const initialToggle = getByLabelText( 'Enable AVS Mismatch filter' );
		expect( initialToggle ).not.toBeChecked();

		// Populated ruleset arrives.
		mockUseAdvancedFraudProtectionSettings.mockReturnValue( [
			[ AVS_RULE ],
			jest.fn(),
		] );
		mockUseSettings.mockReturnValue( {
			settings: {
				advanced_fraud_protection_settings: [ AVS_RULE ],
			},
			saveSettings: jest.fn(),
			isSaving: false,
			isLoading: false,
			isDirty: false,
		} );

		rerender( <FraudProtectionAdvancedSettingsPage /> );

		// Synchronously (no waitFor / findBy) the AVS toggle must now be
		// checked. If this ever fails, the single-pass sync between the
		// prop and `protectionSettingsUI` has regressed and the empty→
		// populated render ordering invariant is no longer preserved.
		const updatedToggle = getByLabelText( 'Enable AVS Mismatch filter' );
		expect( updatedToggle ).toBeChecked();
	} );
} );
